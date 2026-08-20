<?php

namespace Karnoweb\Hr\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Karnoweb\Hr\Enums\DocumentStatus;
use Karnoweb\Hr\Enums\DocumentType;
use Karnoweb\Hr\Enums\OvertimeStatus;
use Karnoweb\Hr\Enums\OvertimeType;
use Karnoweb\Hr\Models\AttendanceRecord;
use Karnoweb\Hr\Models\Employee;
use Karnoweb\Hr\Models\HrDocument;
use Karnoweb\Hr\Models\OvertimeRecord;
use Karnoweb\Hr\Models\PayrollPeriod;
use Karnoweb\Hr\Models\Shift;
use Karnoweb\Hr\Support\HrDocumentReference;
use Karnoweb\Hr\Support\OvertimeMinuteClassifier;
use Karnoweb\Hr\Support\WorkingDayCalculator;

/**
 * Overtime records derived from attendance and approval lifecycle (HR-061–HR-067).
 */
class OvertimeService
{
    public function __construct(
        protected OvertimeMinuteClassifier $classifier,
        protected WorkingDayCalculator $workingDays,
    ) {}

    /**
     * Create or update overtime rows from an attendance record's overtime columns.
     *
     * @return list<OvertimeRecord>
     */
    public function syncFromAttendance(AttendanceRecord $attendance, ?int $hrDocumentId = null): array
    {
        return DB::transaction(function () use ($attendance, $hrDocumentId) {
            $attendance = AttendanceRecord::query()->whereKey($attendance->getKey())->lockForUpdate()->firstOrFail();

            if ($attendance->clock_in === null || $attendance->clock_out === null) {
                throw new InvalidArgumentException('Attendance must be clocked out before syncing overtime.');
            }

            HrDocumentReference::assertValid($hrDocumentId);

            if (config('hr.overtime.requires_pre_approval', false)) {
                $this->assertPreApprovalDocument($hrDocumentId, $attendance);
            }

            $employee = Employee::query()->findOrFail($attendance->employee_id);
            $records = [];

            $buckets = [
                ['type' => OvertimeType::Regular, 'minutes' => (int) ($attendance->overtime_minutes ?? 0)],
                ['type' => OvertimeType::Night, 'minutes' => (int) ($attendance->overtime_night_minutes ?? 0)],
                ['type' => OvertimeType::Holiday, 'minutes' => (int) ($attendance->overtime_holiday_minutes ?? 0)],
            ];

            foreach ($buckets as $bucket) {
                $record = $this->syncTypeRecord(
                    $employee,
                    $attendance,
                    $bucket['type'],
                    $bucket['minutes'],
                    $hrDocumentId
                );

                if ($record !== null) {
                    $records[] = $record;
                }
            }

            return $records;
        });
    }

    /**
     * Compute overtime buckets from clock times and persist on the attendance row.
     *
     * @return array{regular: int, night: int, holiday: int, total: int}
     */
    public function applyOvertimeToAttendance(AttendanceRecord $attendance, ?Shift $shift = null): array
    {
        if ($attendance->clock_in === null || $attendance->clock_out === null) {
            return ['regular' => 0, 'night' => 0, 'holiday' => 0, 'total' => 0];
        }

        $clockIn = Carbon::parse($attendance->clock_in);
        $clockOut = Carbon::parse($attendance->clock_out);
        $attendanceDate = Carbon::parse($attendance->date);

        if ($shift === null) {
            return ['regular' => 0, 'night' => 0, 'holiday' => 0, 'total' => 0];
        }

        [$shiftStart, $shiftEnd] = $this->shiftBounds($attendanceDate, $shift);

        if ($clockOut->lte($shiftEnd)) {
            return ['regular' => 0, 'night' => 0, 'holiday' => 0, 'total' => 0];
        }

        $isHoliday = $this->workingDays->isHoliday(
            $attendanceDate,
            Employee::query()->find($attendance->employee_id)?->branch_id
        );

        $breakdown = $this->classifier->classify($shiftEnd, $clockOut, $attendanceDate, $isHoliday);

        $attendance->forceFill([
            'overtime_minutes' => $breakdown['regular'],
            'overtime_night_minutes' => $breakdown['night'],
            'overtime_holiday_minutes' => $breakdown['holiday'],
        ])->saveQuietly();

        return $breakdown;
    }

    public function approve(OvertimeRecord $record, ?int $approvedBy = null): OvertimeRecord
    {
        return DB::transaction(function () use ($record, $approvedBy) {
            $employee = Employee::query()->whereKey($record->employee_id)->lockForUpdate()->firstOrFail();
            $record = OvertimeRecord::query()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();

            if ($record->status !== OvertimeStatus::Pending) {
                throw new InvalidArgumentException('Only pending overtime records can be approved.');
            }

            if (config('hr.overtime.requires_pre_approval', false)) {
                $attendance = $record->attendance_record_id
                    ? AttendanceRecord::query()->find($record->attendance_record_id)
                    : null;

                $this->assertPreApprovalDocument($record->hr_document_id, $attendance);
            }

            $minutes = (int) $record->calculated_minutes;
            $this->assertMonthlyCap($record, $minutes, $employee);

            $record->update([
                'status' => OvertimeStatus::Approved,
                'approved_minutes' => $minutes,
                'approved_by' => $approvedBy,
                'approved_at' => now(),
            ]);

            return $record->refresh();
        });
    }

    public function reject(OvertimeRecord $record, ?string $notes = null): OvertimeRecord
    {
        return DB::transaction(function () use ($record, $notes) {
            $record = OvertimeRecord::query()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();

            if ($record->status !== OvertimeStatus::Pending) {
                throw new InvalidArgumentException('Only pending overtime records can be rejected.');
            }

            $record->update([
                'status' => OvertimeStatus::Rejected,
                'notes' => $notes ?? $record->notes,
            ]);

            return $record->refresh();
        });
    }

    /**
     * Approved minutes in a payroll period, keyed by overtime type (HR-067).
     *
     * @return array<string, int>
     */
    public function approvedMinutesForPeriod(Employee $employee, PayrollPeriod $period): array
    {
        $totals = [];

        foreach (OvertimeType::cases() as $type) {
            $totals[$type->value] = 0;
        }

        $rows = OvertimeRecord::query()
            ->forEmployee($employee->id)
            ->where('status', OvertimeStatus::Approved)
            ->whereDate('date', '>=', Carbon::parse($period->start_date)->toDateString())
            ->whereDate('date', '<=', Carbon::parse($period->end_date)->toDateString())
            ->get(['type', 'approved_minutes', 'calculated_minutes']);

        foreach ($rows as $row) {
            $type = $row->type instanceof OvertimeType ? $row->type->value : (string) $row->type;
            $totals[$type] = ($totals[$type] ?? 0) + (int) ($row->approved_minutes ?? $row->calculated_minutes);
        }

        return $totals;
    }

    public function approvedMinutesForMonth(Employee $employee, Carbon $date): int
    {
        $start = $date->copy()->startOfMonth();
        $end = $date->copy()->endOfMonth();

        return (int) OvertimeRecord::query()
            ->forEmployee($employee->id)
            ->where('status', OvertimeStatus::Approved)
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->sum('approved_minutes');
    }

    protected function syncTypeRecord(
        Employee $employee,
        AttendanceRecord $attendance,
        OvertimeType $type,
        int $minutes,
        ?int $hrDocumentId
    ): ?OvertimeRecord {
        $minMinutes = (int) config('hr.overtime.min_minutes', 0);

        $existing = OvertimeRecord::query()
            ->where('attendance_record_id', $attendance->id)
            ->where('type', $type)
            ->first();

        if ($minutes < $minMinutes) {
            if ($existing !== null && $existing->status === OvertimeStatus::Pending) {
                $existing->delete();
            }

            return null;
        }

        return OvertimeRecord::query()->updateOrCreate(
            [
                'attendance_record_id' => $attendance->id,
                'type' => $type,
            ],
            [
                'employee_id' => $employee->id,
                'date' => $attendance->date,
                'calculated_minutes' => $minutes,
                'approved_minutes' => null,
                'status' => OvertimeStatus::Pending,
                'hr_document_id' => $hrDocumentId,
            ]
        );
    }

    protected function assertMonthlyCap(OvertimeRecord $record, int $additionalMinutes, ?Employee $employee = null): void
    {
        $cap = (int) config('hr.overtime.monthly_cap', 0);

        if ($cap <= 0) {
            return;
        }

        $employee ??= Employee::query()->whereKey($record->employee_id)->lockForUpdate()->firstOrFail();
        $used = $this->approvedMinutesForMonth($employee, Carbon::parse($record->date));

        if ($used + $additionalMinutes > $cap) {
            throw new InvalidArgumentException(
                "Approving this overtime would exceed the monthly cap of {$cap} minutes (current: {$used})."
            );
        }
    }

    protected function assertPreApprovalDocument(?int $hrDocumentId, ?AttendanceRecord $attendance): void
    {
        if ($hrDocumentId === null) {
            throw new InvalidArgumentException(
                'Pre-approved OvertimeApproval document is required when hr.overtime.requires_pre_approval is enabled.'
            );
        }

        $document = HrDocument::query()->whereKey($hrDocumentId)->first();

        if ($document === null) {
            throw new InvalidArgumentException('Pre-approval HR document not found.');
        }

        if ($document->type !== DocumentType::OvertimeApproval) {
            throw new InvalidArgumentException('Linked HR document must be of type overtime_approval.');
        }

        if ($document->status !== DocumentStatus::Approved) {
            throw new InvalidArgumentException('Pre-approval HR document must already be approved.');
        }

        if ($attendance !== null && $document->effective_date->gt($attendance->date)) {
            throw new InvalidArgumentException(
                'Pre-approval document effective_date must be on or before the overtime work date.'
            );
        }
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
        $string = (string) $time;

        return strlen($string) === 5 ? $string.':00' : $string;
    }
}
