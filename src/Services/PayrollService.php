<?php

namespace Karnoweb\Hr\Services;

use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Karnoweb\Hr\Enums\EmployeeStatus;
use Karnoweb\Hr\Enums\PayrollPeriodStatus;
use Karnoweb\Hr\Enums\PayrollRecordStatus;
use Karnoweb\Hr\Exceptions\PayrollPeriodExistsException;
use Karnoweb\Hr\Exceptions\PayrollPeriodLockedException;
use Karnoweb\Hr\Models\Employee;
use Karnoweb\Hr\Models\LoanPayment;
use Karnoweb\Hr\Models\PayrollPeriod;
use Karnoweb\Hr\Models\PayrollRecord;
use Karnoweb\Hr\Support\QueryExceptionClassifier;
use Karnoweb\Hr\Support\WorkingDayCalculator;

/**
 * Payroll period lifecycle: open, calculate, approve, mark paid (HR-089–HR-101).
 */
class PayrollService
{
    public function __construct(
        protected WorkingDayCalculator $workingDays,
        protected PayrollCalculator $calculator,
        protected LoanService $loans,
    ) {}

    public function openPeriod(?int $branchId, int $year, int $month): PayrollPeriod
    {
        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth();

        try {
            return PayrollPeriod::query()->create([
                'branch_id' => $branchId,
                'year' => $year,
                'month' => $month,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'working_days' => $this->workingDays->count($start, $end, $branchId),
                'status' => PayrollPeriodStatus::Draft,
            ]);
        } catch (QueryException $e) {
            if (QueryExceptionClassifier::isUniqueViolation($e)) {
                throw new PayrollPeriodExistsException(
                    "Payroll period already exists for branch [{$branchId}] {$year}-{$month}.",
                    previous: $e
                );
            }

            throw $e;
        }
    }

    public function calculate(PayrollPeriod $period): PayrollPeriod
    {
        return DB::transaction(function () use ($period) {
            $period = PayrollPeriod::query()->whereKey($period->getKey())->lockForUpdate()->firstOrFail();

            $this->assertCanEdit($period);

            if (! $period->status->canCalculate()) {
                throw new PayrollPeriodLockedException(
                    "Payroll period cannot be calculated in status [{$period->status->value}]."
                );
            }

            $period->update(['status' => PayrollPeriodStatus::Calculating]);

            $employees = Employee::query()
                ->when($period->branch_id !== null, fn ($query) => $query->where('branch_id', $period->branch_id))
                ->where('status', EmployeeStatus::Active)
                ->get();

            foreach ($employees as $employee) {
                DB::transaction(function () use ($period, $employee) {
                    $payload = $this->calculator->calculateEmployee($employee, $period);

                    PayrollRecord::query()->updateOrCreate(
                        [
                            'payroll_period_id' => $period->id,
                            'employee_id' => $employee->id,
                        ],
                        $payload
                    );
                });
            }

            $period->update([
                'status' => PayrollPeriodStatus::Calculated,
                'calculated_at' => now(),
            ]);

            return $period->refresh()->load('records');
        });
    }

    public function approve(PayrollPeriod $period, ?int $approvedBy = null): PayrollPeriod
    {
        return DB::transaction(function () use ($period, $approvedBy) {
            $period = PayrollPeriod::query()->whereKey($period->getKey())->lockForUpdate()->firstOrFail();

            if (! $period->status->canApprove()) {
                throw new PayrollPeriodLockedException(
                    "Payroll period cannot be approved in status [{$period->status->value}]."
                );
            }

            $records = PayrollRecord::query()
                ->where('payroll_period_id', $period->id)
                ->lockForUpdate()
                ->get();

            foreach ($records as $record) {
                $this->commitLoanDeductions($record);

                $record->update(['status' => PayrollRecordStatus::Approved]);
            }

            $period->update([
                'status' => PayrollPeriodStatus::Approved,
                'approved_at' => now(),
                'approved_by' => $approvedBy,
            ]);

            return $period->refresh()->load('records');
        });
    }

    public function markPaid(PayrollPeriod $period): PayrollPeriod
    {
        return DB::transaction(function () use ($period) {
            $period = PayrollPeriod::query()->whereKey($period->getKey())->lockForUpdate()->firstOrFail();

            if ($period->status !== PayrollPeriodStatus::Approved) {
                throw new PayrollPeriodLockedException(
                    'Payroll period must be approved before it can be marked paid.'
                );
            }

            PayrollRecord::query()
                ->where('payroll_period_id', $period->id)
                ->update(['status' => PayrollRecordStatus::Paid]);

            $period->update([
                'status' => PayrollPeriodStatus::Paid,
                'paid_at' => now(),
            ]);

            return $period->refresh()->load('records');
        });
    }

    protected function assertCanEdit(PayrollPeriod $period): void
    {
        if (! $period->status->canEdit()) {
            throw new PayrollPeriodLockedException(
                "Payroll period is locked for edits in status [{$period->status->value}]."
            );
        }
    }

    protected function commitLoanDeductions(PayrollRecord $record): void
    {
        $loanPayments = $record->calculation_log['loan_payments'] ?? [];

        foreach ($loanPayments as $entry) {
            $payment = LoanPayment::query()->whereKey($entry['id'] ?? null)->lockForUpdate()->first();

            if ($payment === null) {
                continue;
            }

            $this->loans->recordPayment($payment, now(), $record->id);
        }
    }
}
