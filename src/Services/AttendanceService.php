<?php

namespace Karnoweb\Hr\Services;

use DateTimeInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Karnoweb\Hr\Enums\AttendanceStatus;
use Karnoweb\Hr\Exceptions\HrException;
use Karnoweb\Hr\Models\AttendanceRecord;
use Karnoweb\Hr\Models\Employee;
use Karnoweb\Hr\Models\Shift;
use Karnoweb\Hr\Support\WorkingDayCalculator;

/**
 * Attendance clock-in/out and manual corrections (HR-035, HR-037, HR-040).
 *
 * Overnight-shift policy: the attendance `date` is always the calendar date of
 * clock-in. A night shift spanning midnight stays on one row keyed by that date.
 */
class AttendanceService
{
    public function __construct(
        protected ShiftResolver $shiftResolver,
        protected WorkingDayCalculator $workingDayCalculator,
    ) {}

    /**
     * Clock in for today (or the given instant).
     *
     * Reuses an existing open record for the same employee/date when clock_in is still null.
     */
    public function clockIn(Employee $employee, DateTimeInterface|string|null $at = null, array $options = []): AttendanceRecord
    {
        $clockIn = Carbon::parse($at ?? now());
        $attendanceDate = $this->attendanceDateForClockIn($clockIn);

        return DB::transaction(function () use ($employee, $clockIn, $attendanceDate, $options) {
            $employee = Employee::query()->whereKey($employee->getKey())->lockForUpdate()->firstOrFail();

            $record = AttendanceRecord::query()
                ->where('employee_id', $employee->id)
                ->whereDate('date', $attendanceDate->toDateString())
                ->lockForUpdate()
                ->first();

            if ($record !== null && $record->clock_in !== null) {
                throw new InvalidArgumentException('Employee is already clocked in for this attendance date.');
            }

            $shift = $this->shiftResolver->resolve($employee, $attendanceDate);

            $payload = [
                'employee_id' => $employee->id,
                'date' => $attendanceDate->toDateString(),
                'clock_in' => $clockIn,
                'shift_id' => $shift?->id,
                'status' => AttendanceStatus::Present,
                'source' => $options['source'] ?? 'manual',
                'notes' => $options['notes'] ?? null,
            ];

            if ($record === null) {
                try {
                    return AttendanceRecord::query()->create($payload);
                } catch (QueryException $e) {
                    throw new InvalidArgumentException(
                        'An attendance record already exists for this employee and date.',
                        previous: $e
                    );
                }
            }

            $record->update($payload);

            return $record->refresh();
        });
    }

    /**
     * Clock out, computing work/late/early-leave minutes from the resolved shift.
     */
    public function clockOut(Employee $employee, DateTimeInterface|string|null $at = null, array $options = []): AttendanceRecord
    {
        $clockOut = Carbon::parse($at ?? now());

        return DB::transaction(function () use ($employee, $clockOut, $options) {
            $employee = Employee::query()->whereKey($employee->getKey())->lockForUpdate()->firstOrFail();

            $record = AttendanceRecord::query()
                ->where('employee_id', $employee->id)
                ->whereNotNull('clock_in')
                ->whereNull('clock_out')
                ->orderByDesc('clock_in')
                ->lockForUpdate()
                ->first();

            if ($record === null) {
                throw new InvalidArgumentException('Employee has no open clock-in to clock out from.');
            }

            return $this->finalizeClockOut($record, $clockOut, $options);
        });
    }

    /**
     * Clock out a specific open attendance row (used by auto clock-out job).
     */
    public function clockOutRecord(AttendanceRecord $record, DateTimeInterface|string $at, array $options = []): AttendanceRecord
    {
        $clockOut = Carbon::parse($at);

        return DB::transaction(function () use ($record, $clockOut, $options) {
            $record = AttendanceRecord::query()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();

            if ($record->clock_in === null || $record->clock_out !== null) {
                throw new InvalidArgumentException('Attendance record is not open for clock-out.');
            }

            return $this->finalizeClockOut($record, $clockOut, $options);
        });
    }

    protected function finalizeClockOut(AttendanceRecord $record, Carbon $clockOut, array $options): AttendanceRecord
    {
        if ($clockOut->lt($record->clock_in)) {
            throw new InvalidArgumentException('Clock-out time must be after clock-in time.');
        }

        $employee = Employee::query()->findOrFail($record->employee_id);

        $shift = $record->shift_id
            ? Shift::query()->find($record->shift_id)
            : $this->shiftResolver->resolve($employee, Carbon::parse($record->date));

        $metrics = $this->computeMetrics(
            Carbon::parse($record->clock_in),
            $clockOut,
            Carbon::parse($record->date),
            $shift
        );

        $record->update([
            'clock_out' => $clockOut,
            'shift_id' => $shift !== null ? $shift->id : $record->shift_id,
            'work_minutes' => $metrics['work_minutes'],
            'late_minutes' => $metrics['late_minutes'],
            'early_leave_minutes' => $metrics['early_leave_minutes'],
            'status' => AttendanceStatus::Present,
            'source' => $options['source'] ?? $record->source,
            'notes' => $options['notes'] ?? $record->notes,
        ]);

        return $record->refresh();
    }

    /**
     * Ensure a placeholder record exists for a date with Holiday/Weekend status when appropriate (HR-040).
     */
    public function ensureDayRecord(Employee $employee, DateTimeInterface|string $date): AttendanceRecord
    {
        $day = Carbon::parse($date)->startOfDay();

        return DB::transaction(function () use ($employee, $day) {
            $existing = AttendanceRecord::query()
                ->where('employee_id', $employee->id)
                ->whereDate('date', $day->toDateString())
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $status = $this->resolveInitialStatus($employee, $day);

            return AttendanceRecord::query()->create([
                'employee_id' => $employee->id,
                'date' => $day->toDateString(),
                'status' => $status,
                'source' => 'system',
            ]);
        });
    }

    /**
     * Apply a manual correction with an audit trail stored in raw_data (HR-042).
     *
     * Direct Eloquent updates bypass this trail by design.
     *
     * @param  array<string, mixed>  $changes
     *
     * @throws HrException When config requires approval and workflow is not yet wired (HR-043 / Phase 11).
     */
    public function adjust(
        Employee $employee,
        DateTimeInterface|string $date,
        array $changes,
        ?int $adjustedBy = null
    ): AttendanceRecord {
        if (config('hr.attendance.corrections.require_approval', false)) {
            throw new HrException(
                'Attendance corrections requiring approval are not yet available. Enable after Phase 11 workflow integration (HR-043).'
            );
        }

        $day = Carbon::parse($date)->startOfDay();

        return DB::transaction(function () use ($employee, $day, $changes, $adjustedBy) {
            $record = AttendanceRecord::query()
                ->where('employee_id', $employee->id)
                ->whereDate('date', $day->toDateString())
                ->lockForUpdate()
                ->first();

            if ($record === null) {
                throw new InvalidArgumentException('No attendance record exists for this date.');
            }

            $allowed = [
                'clock_in', 'clock_out', 'work_minutes', 'late_minutes',
                'early_leave_minutes', 'status', 'notes', 'shift_id',
            ];

            $filtered = array_intersect_key($changes, array_flip($allowed));

            if ($filtered === []) {
                throw new InvalidArgumentException('No valid attendance fields were provided to adjust.');
            }

            $before = array_intersect_key($record->getAttributes(), $filtered);
            $audit = $record->raw_data['adjustments'] ?? [];
            $audit[] = [
                'at' => now()->toIso8601String(),
                'by' => $adjustedBy,
                'before' => $before,
                'changes' => $filtered,
            ];

            $record->update(array_merge($filtered, [
                'raw_data' => array_merge($record->raw_data ?? [], ['adjustments' => $audit]),
                'source' => 'manual_correction',
            ]));

            return $record->refresh();
        });
    }

    public function resolveInitialStatus(Employee $employee, DateTimeInterface|string $date): AttendanceStatus
    {
        $day = Carbon::parse($date)->startOfDay();
        if ($this->workingDayCalculator->isHoliday($day, $employee->branch_id)) {
            return AttendanceStatus::Holiday;
        }

        if (! $this->workingDayCalculator->isWorkingDay($day, $employee->branch_id)) {
            return AttendanceStatus::Weekend;
        }

        return AttendanceStatus::Absent;
    }

    /**
     * Calendar date used as attendance_records.date — always clock-in date (HR-037).
     */
    public function attendanceDateForClockIn(Carbon $clockIn): Carbon
    {
        return $clockIn->copy()->startOfDay();
    }

    /**
     * @return array{work_minutes: int, late_minutes: int, early_leave_minutes: int}
     */
    public function computeMetrics(Carbon $clockIn, Carbon $clockOut, Carbon $attendanceDate, ?Shift $shift): array
    {
        $workMinutes = max(0, (int) $clockIn->diffInMinutes($clockOut));

        if ($shift === null) {
            return [
                'work_minutes' => $workMinutes,
                'late_minutes' => 0,
                'early_leave_minutes' => 0,
            ];
        }

        $lateTolerance = (int) config('hr.attendance.late_tolerance_minutes', 0);
        $earlyTolerance = (int) config('hr.attendance.early_leave_tolerance_minutes', 0);

        [$shiftStart, $shiftEnd] = $this->shiftBounds($attendanceDate, $shift);

        $lateMinutes = 0;
        $lateThreshold = $shiftStart->copy()->addMinutes($lateTolerance);

        if ($clockIn->gt($lateThreshold)) {
            $lateMinutes = max(0, (int) $shiftStart->diffInMinutes($clockIn));
        }

        $earlyLeaveMinutes = 0;
        $earlyThreshold = $shiftEnd->copy()->subMinutes($earlyTolerance);

        if ($clockOut->lt($earlyThreshold)) {
            $earlyLeaveMinutes = max(0, (int) $clockOut->diffInMinutes($shiftEnd));
        }

        if ($shift->break_start && $shift->break_end) {
            $breakMinutes = $this->breakOverlapMinutes($clockIn, $clockOut, $attendanceDate, $shift);
            $workMinutes = max(0, $workMinutes - $breakMinutes);
        }

        return [
            'work_minutes' => $workMinutes,
            'late_minutes' => $lateMinutes,
            'early_leave_minutes' => $earlyLeaveMinutes,
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function shiftBounds(Carbon $attendanceDate, Shift $shift): array
    {
        $start = Carbon::parse($attendanceDate->toDateString().' '.$this->normalizeTime($shift->start_time));
        $end = Carbon::parse($attendanceDate->toDateString().' '.$this->normalizeTime($shift->end_time));

        if ($shift->is_night_shift || $end->lte($start)) {
            $end->addDay();
        }

        return [$start, $end];
    }

    protected function normalizeTime(mixed $time): string
    {
        if ($time instanceof Carbon) {
            return $time->format('H:i:s');
        }

        $string = (string) $time;

        return strlen($string) === 5 ? $string.':00' : $string;
    }

    protected function breakOverlapMinutes(Carbon $clockIn, Carbon $clockOut, Carbon $attendanceDate, Shift $shift): int
    {
        $breakStart = Carbon::parse($attendanceDate->toDateString().' '.$this->normalizeTime($shift->break_start));
        $breakEnd = Carbon::parse($attendanceDate->toDateString().' '.$this->normalizeTime($shift->break_end));

        if ($breakEnd->lte($breakStart)) {
            $breakEnd->addDay();
        }

        $overlapStart = $clockIn->greaterThan($breakStart) ? $clockIn : $breakStart;
        $overlapEnd = $clockOut->lessThan($breakEnd) ? $clockOut : $breakEnd;

        if ($overlapEnd->lte($overlapStart)) {
            return 0;
        }

        return (int) $overlapStart->diffInMinutes($overlapEnd);
    }

    /**
     * Mark working days in a range with leave/mission status (HR-058).
     *
     * Skips days that already have clock-in data (explicit attendance overrides).
     */
    public function markStatusForWorkingDays(
        Employee $employee,
        DateTimeInterface|string $start,
        DateTimeInterface|string $end,
        AttendanceStatus $status
    ): void {
        $start = Carbon::parse($start)->startOfDay();
        $end = Carbon::parse($end)->startOfDay();

        foreach ($this->workingDayCalculator->dates($start, $end, $employee->branch_id) as $day) {
            $record = AttendanceRecord::query()
                ->where('employee_id', $employee->id)
                ->whereDate('date', $day->toDateString())
                ->first();

            if ($record !== null && $record->clock_in !== null) {
                continue;
            }

            AttendanceRecord::query()->updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'date' => $day->toDateString(),
                ],
                [
                    'status' => $status,
                    'source' => $status === AttendanceStatus::Leave ? 'leave' : 'mission',
                ]
            );
        }
    }

    /**
     * Restore placeholder statuses after a cancelled future leave/mission.
     */
    public function revertStatusForWorkingDays(
        Employee $employee,
        DateTimeInterface|string $start,
        DateTimeInterface|string $end
    ): void {
        $start = Carbon::parse($start)->startOfDay();
        $end = Carbon::parse($end)->startOfDay();

        foreach ($this->workingDayCalculator->dates($start, $end, $employee->branch_id) as $day) {
            $record = AttendanceRecord::query()
                ->where('employee_id', $employee->id)
                ->whereDate('date', $day->toDateString())
                ->first();

            if ($record === null || $record->clock_in !== null) {
                continue;
            }

            $record->update([
                'status' => $this->resolveInitialStatus($employee, $day),
                'source' => 'system',
            ]);
        }
    }
}
