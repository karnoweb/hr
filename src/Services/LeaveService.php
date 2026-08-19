<?php

namespace Karnoweb\Hr\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Karnoweb\Hr\Enums\AttendanceStatus;
use Karnoweb\Hr\Enums\DocumentType;
use Karnoweb\Hr\Enums\LeaveRequestStatus;
use Karnoweb\Hr\Exceptions\InsufficientLeaveBalanceException;
use Karnoweb\Hr\Models\DocumentAttachment;
use Karnoweb\Hr\Models\Employee;
use Karnoweb\Hr\Models\LeaveBalance;
use Karnoweb\Hr\Models\LeaveRequest;
use Karnoweb\Hr\Support\DateRangeOverlap;
use Karnoweb\Hr\Support\HrDocumentReference;
use Karnoweb\Hr\Support\WorkingDayCalculator;

/**
 * Leave requests, balance validation, approval lifecycle (HR-047–HR-054).
 */
class LeaveService
{
    public function __construct(
        protected LeaveBalanceService $balances,
        protected WorkingDayCalculator $workingDays,
        protected AttendanceService $attendance,
        protected DocumentService $documents,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array{create_document?: bool, use_calculated_days?: bool}  $options
     */
    public function request(Employee $employee, array $data, array $options = []): LeaveRequest
    {
        $type = (string) ($data['type'] ?? '');

        if ($this->balances->typeConfig($type) === null) {
            throw new InvalidArgumentException("Unknown leave type [{$type}].");
        }

        $start = Carbon::parse($data['start_date'])->startOfDay();
        $end = Carbon::parse($data['end_date'] ?? $data['start_date'])->startOfDay();

        if ($end->lt($start)) {
            throw new InvalidArgumentException('end_date must be on or after start_date.');
        }

        $useCalculatedDays = $options['use_calculated_days'] ?? ! isset($data['days']);
        $days = $useCalculatedDays
            ? (float) $this->workingDays->count($start, $end, $employee->branch_id)
            : (float) ($data['days'] ?? 0);

        $hours = (float) ($data['hours'] ?? 0);
        $year = (int) $start->year;

        $this->assertFixedDaysRule($type, $days);
        $this->assertOncePerEmploymentRule($employee, $type);

        return DB::transaction(function () use ($employee, $data, $type, $start, $end, $days, $hours, $year, $options) {
            $employee = Employee::query()->whereKey($employee->getKey())->lockForUpdate()->firstOrFail();

            $this->assertNoOverlap($employee, $start, $end);

            if ($type === 'hourly') {
                $this->assertHourlyMonthlyCap($employee, $start, $hours);
            } elseif ($this->balances->usesDayBalance($type)) {
                $this->assertSufficientBalance($employee, $year, $type, $days);
            }

            HrDocumentReference::assertValid(isset($data['hr_document_id']) ? (int) $data['hr_document_id'] : null);

            $payload = array_merge($data, [
                'employee_id' => $employee->id,
                'type' => $type,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'days' => $days,
                'hours' => $hours,
                'status' => LeaveRequestStatus::Pending,
            ]);

            $request = LeaveRequest::query()->create($payload);

            if ($options['create_document'] ?? false) {
                $document = $this->documents->create(DocumentType::Leave, $employee, [
                    'leave_request_id' => $request->id,
                    'type' => $type,
                    'start_date' => $start->toDateString(),
                    'end_date' => $end->toDateString(),
                    'days' => $days,
                    'hours' => $hours,
                ]);

                $request->update(['hr_document_id' => $document->id]);
                $request = $request->refresh();
            }

            return $request;
        });
    }

    public function approve(LeaveRequest $request): LeaveRequest
    {
        return DB::transaction(function () use ($request) {
            $request = LeaveRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();

            if ($request->status !== LeaveRequestStatus::Pending) {
                throw new InvalidArgumentException('Only pending leave requests can be approved.');
            }

            $employee = Employee::query()->findOrFail($request->employee_id);
            $typeConfig = $this->balances->typeConfig($request->type) ?? [];

            $this->assertRequiredDocumentPresent($request, $typeConfig);

            if ($request->type === 'hourly') {
                $this->assertHourlyMonthlyCap($employee, Carbon::parse($request->start_date), (float) $request->hours, $request->id);
            } elseif ($this->balances->usesDayBalance($request->type) && ($typeConfig['paid'] ?? false)) {
                $balance = $this->balances->ensureBalance(
                    $employee,
                    (int) Carbon::parse($request->start_date)->year,
                    $request->type
                );

                $balance = LeaveBalance::query()->whereKey($balance->getKey())->lockForUpdate()->firstOrFail();

                if ((float) $request->days > (float) $balance->remaining_days) {
                    throw new InsufficientLeaveBalanceException(
                        "Insufficient leave balance to approve request (need {$request->days}, remaining {$balance->remaining_days})."
                    );
                }

                $this->balances->decrement($balance, (float) $request->days);
            }

            $request->update(['status' => LeaveRequestStatus::Approved]);

            $this->attendance->markStatusForWorkingDays(
                $employee,
                Carbon::parse($request->start_date),
                Carbon::parse($request->end_date),
                AttendanceStatus::Leave
            );

            return $request->refresh();
        });
    }

    public function reject(LeaveRequest $request, ?string $reason = null): LeaveRequest
    {
        return DB::transaction(function () use ($request, $reason) {
            $request = LeaveRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();

            if ($request->status !== LeaveRequestStatus::Pending) {
                throw new InvalidArgumentException('Only pending leave requests can be rejected.');
            }

            $request->update([
                'status' => LeaveRequestStatus::Rejected,
                'reason' => $reason ?? $request->reason,
            ]);

            return $request->refresh();
        });
    }

    public function cancel(LeaveRequest $request, ?string $reason = null): LeaveRequest
    {
        return DB::transaction(function () use ($request, $reason) {
            $request = LeaveRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();
            $employee = Employee::query()->findOrFail($request->employee_id);
            $today = Carbon::now()->startOfDay();

            if ($request->status === LeaveRequestStatus::Pending) {
                $request->update([
                    'status' => LeaveRequestStatus::Cancelled,
                    'reason' => $reason ?? $request->reason,
                ]);

                return $request->refresh();
            }

            if ($request->status === LeaveRequestStatus::Approved) {
                if (Carbon::parse($request->start_date)->lte($today)) {
                    throw new InvalidArgumentException(
                        'Approved leave that has already started cannot be cancelled through this method.'
                    );
                }

                $typeConfig = $this->balances->typeConfig($request->type) ?? [];

                if ($this->balances->usesDayBalance($request->type) && ($typeConfig['paid'] ?? false)) {
                    $balance = $this->balances->lockBalance(
                        $employee,
                        (int) Carbon::parse($request->start_date)->year,
                        $request->type
                    );

                    if ($balance !== null) {
                        $this->balances->increment($balance, (float) $request->days);
                    }
                }

                $this->attendance->revertStatusForWorkingDays(
                    $employee,
                    Carbon::parse($request->start_date),
                    Carbon::parse($request->end_date)
                );

                $request->update([
                    'status' => LeaveRequestStatus::Cancelled,
                    'reason' => $reason ?? $request->reason,
                ]);

                return $request->refresh();
            }

            throw new InvalidArgumentException('Leave request cannot be cancelled in its current status.');
        });
    }

    public function balance(Employee $employee, int $year, string $type): ?LeaveBalance
    {
        return LeaveBalance::query()
            ->forEmployee($employee->id)
            ->forYear($year)
            ->where('type', $type)
            ->first();
    }

    protected function assertSufficientBalance(Employee $employee, int $year, string $type, float $days): void
    {
        $balance = $this->balances->ensureBalance($employee, $year, $type);
        $balance = LeaveBalance::query()->whereKey($balance->getKey())->lockForUpdate()->firstOrFail();

        $pending = $this->balances->pendingReservedDays($employee, $year, $type);
        $available = (float) $balance->remaining_days - $pending;

        if ($days > $available) {
            throw new InsufficientLeaveBalanceException(
                "Insufficient leave balance (requested {$days}, available {$available} after pending requests)."
            );
        }
    }

    protected function assertNoOverlap(Employee $employee, Carbon $start, Carbon $end, ?int $excludeId = null): void
    {
        $query = LeaveRequest::query()
            ->forEmployee($employee->id)
            ->whereIn('status', [LeaveRequestStatus::Pending, LeaveRequestStatus::Approved]);

        if ($excludeId !== null) {
            $query->whereKeyNot($excludeId);
        }

        $existing = $query->get(['start_date', 'end_date']);

        foreach ($existing as $row) {
            if (DateRangeOverlap::rangesOverlap($start, $end, $row->start_date, $row->end_date)) {
                throw new InvalidArgumentException(
                    'Leave request overlaps an existing pending or approved request.'
                );
            }
        }
    }

    protected function assertFixedDaysRule(string $type, float $days): void
    {
        $config = $this->balances->typeConfig($type);

        if ($config === null || ! isset($config['fixed_days'])) {
            return;
        }

        $fixed = (float) $config['fixed_days'];

        if (abs($days - $fixed) > 0.001) {
            throw new InvalidArgumentException(
                "Leave type [{$type}] requires exactly {$fixed} day(s); {$days} given."
            );
        }
    }

    protected function assertOncePerEmploymentRule(Employee $employee, string $type): void
    {
        $config = $this->balances->typeConfig($type);

        if ($config === null || ! ($config['once_per_employment'] ?? false)) {
            return;
        }

        $exists = LeaveRequest::query()
            ->forEmployee($employee->id)
            ->where('type', $type)
            ->whereIn('status', [LeaveRequestStatus::Pending, LeaveRequestStatus::Approved])
            ->exists();

        if ($exists) {
            throw new InvalidArgumentException(
                "Leave type [{$type}] may only be used once per employment."
            );
        }
    }

    protected function assertHourlyMonthlyCap(
        Employee $employee,
        Carbon $date,
        float $hours,
        ?int $excludeRequestId = null
    ): void {
        $max = (float) config('hr.leave.types.hourly.max_hours_per_month', 12);
        $monthStart = $date->copy()->startOfMonth();
        $monthEnd = $date->copy()->endOfMonth();

        $query = LeaveRequest::query()
            ->forEmployee($employee->id)
            ->where('type', 'hourly')
            ->whereIn('status', [LeaveRequestStatus::Pending, LeaveRequestStatus::Approved])
            ->whereDate('start_date', '>=', $monthStart->toDateString())
            ->whereDate('start_date', '<=', $monthEnd->toDateString());

        if ($excludeRequestId !== null) {
            $query->whereKeyNot($excludeRequestId);
        }

        $used = (float) $query->sum('hours');

        if ($used + $hours > $max) {
            throw new InvalidArgumentException(
                "Hourly leave would exceed the monthly cap of {$max} hours."
            );
        }
    }

    /**
     * @param  array<string, mixed>  $typeConfig
     */
    protected function assertRequiredDocumentPresent(LeaveRequest $request, array $typeConfig): void
    {
        if (! ($typeConfig['requires_document'] ?? false)) {
            return;
        }

        if ($request->hr_document_id === null) {
            throw new InvalidArgumentException(
                'This leave type requires a linked HR document with attachments before approval.'
            );
        }

        $hasAttachment = DocumentAttachment::query()
            ->where('hr_document_id', $request->hr_document_id)
            ->exists();

        if (! $hasAttachment) {
            throw new InvalidArgumentException(
                'This leave type requires at least one document attachment before approval.'
            );
        }
    }
}
