<?php

namespace Karnoweb\Hr\Services;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Karnoweb\Hr\Enums\LoanPaymentStatus;
use Karnoweb\Hr\Enums\LoanStatus;
use Karnoweb\Hr\Events\LoanDisbursed;
use Karnoweb\Hr\Exceptions\InvalidEmployeeLifecycleException;
use Karnoweb\Hr\Models\Employee;
use Karnoweb\Hr\Models\Loan;
use Karnoweb\Hr\Models\LoanPayment;
use Karnoweb\Hr\Models\PayrollPeriod;
use Karnoweb\Hr\Support\AccountingEventDispatcher;
use Karnoweb\Hr\Support\HrDocumentReference;
use Karnoweb\Hr\Support\SequenceGenerator;

/**
 * Loan application, approval, repayment, and payroll deduction hooks (HR-080–HR-087).
 *
 * Salary percentage limit (HR-083): `max_percentage_of_salary` is checked against
 * the employee's current `EmployeeSalary.base_salary` (not gross earnings).
 *
 * Cooldown (HR-082): `min_months_between_loans` is measured from the `start_date`
 * of the employee's most recent loan with status Active or Completed.
 */
class LoanService
{
    public function __construct(
        protected SequenceGenerator $sequences,
        protected SalaryService $salaries,
    ) {}

    /**
     * Submit a loan application (status Pending). Limits are read from config at call time.
     *
     * @param  array<string, mixed>  $data
     */
    public function apply(Employee $employee, array $data): Loan
    {
        return DB::transaction(function () use ($employee, $data) {
            $employee = Employee::query()->whereKey($employee->getKey())->lockForUpdate()->firstOrFail();

            [$amount, $installments, $installmentAmount, $startDate] = $this->normalizeApplication($data);

            $this->assertApplicationLimits($employee, $amount, $installments, $installmentAmount, $startDate, null, false);

            HrDocumentReference::assertValid(isset($data['hr_document_id']) ? (int) $data['hr_document_id'] : null);

            return Loan::query()->create([
                'employee_id' => $employee->id,
                'hr_document_id' => $data['hr_document_id'] ?? null,
                'loan_number' => $data['loan_number'] ?? $this->generateLoanNumber($startDate),
                'type' => $data['type'] ?? 'general',
                'amount' => $amount,
                'installments' => $installments,
                'installment_amount' => $installmentAmount,
                'remaining_amount' => $amount,
                'remaining_installments' => $installments,
                'start_date' => $startDate->toDateString(),
                'end_date' => null,
                'status' => LoanStatus::Pending,
                'purpose' => $data['purpose'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
        });
    }

    /**
     * Approve a pending loan and generate its installment schedule atomically.
     */
    public function approve(Loan $loan): Loan
    {
        return DB::transaction(function () use ($loan) {
            $loan = Loan::query()->whereKey($loan->getKey())->lockForUpdate()->firstOrFail();

            if ($loan->status !== LoanStatus::Pending) {
                throw new InvalidArgumentException('Only pending loans can be approved.');
            }

            $employee = Employee::query()->whereKey($loan->employee_id)->lockForUpdate()->firstOrFail();

            $this->assertApplicationLimits(
                $employee,
                (float) $loan->amount,
                (int) $loan->installments,
                (float) $loan->installment_amount,
                Carbon::parse($loan->start_date),
                $loan->id
            );

            $this->generatePaymentSchedule($loan);

            $loan->update(['status' => LoanStatus::Active]);

            $loan = $loan->refresh()->load('payments');

            AccountingEventDispatcher::dispatch(LoanDisbursed::fromLoan($loan));

            return $loan;
        });
    }

    public function reject(Loan $loan, ?string $reason = null): Loan
    {
        return DB::transaction(function () use ($loan, $reason) {
            $loan = Loan::query()->whereKey($loan->getKey())->lockForUpdate()->firstOrFail();

            if ($loan->status !== LoanStatus::Pending) {
                throw new InvalidArgumentException('Only pending loans can be rejected.');
            }

            $loan->update([
                'status' => LoanStatus::Cancelled,
                'notes' => $reason ?? $loan->notes,
            ]);

            return $loan->refresh();
        });
    }

    /**
     * Mark an installment paid and keep loan remaining fields in sync (HR-085).
     */
    public function recordPayment(
        LoanPayment $payment,
        DateTimeInterface|string|null $paidDate = null,
        ?int $payrollRecordId = null
    ): LoanPayment {
        return DB::transaction(function () use ($payment, $paidDate, $payrollRecordId) {
            $payment = LoanPayment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();
            $loan = Loan::query()->whereKey($payment->loan_id)->lockForUpdate()->firstOrFail();

            if ($loan->status !== LoanStatus::Active) {
                throw new InvalidArgumentException('Payments can only be recorded on active loans.');
            }

            if ($payment->status !== LoanPaymentStatus::Pending) {
                throw new InvalidArgumentException('Only pending installments can be marked paid.');
            }

            $paidAt = Carbon::parse($paidDate ?? now())->startOfDay();
            $amount = (float) $payment->amount;

            $payment->update([
                'status' => LoanPaymentStatus::Paid,
                'paid_date' => $paidAt->toDateString(),
                'payroll_record_id' => $payrollRecordId ?? $payment->payroll_record_id,
            ]);

            $remainingAmount = max(0, round((float) $loan->remaining_amount - $amount, 2));
            $remainingInstallments = max(0, (int) $loan->remaining_installments - 1);

            $updates = [
                'remaining_amount' => $remainingAmount,
                'remaining_installments' => $remainingInstallments,
            ];

            if ($remainingInstallments === 0 || $remainingAmount <= 0) {
                $updates['status'] = LoanStatus::Completed;
                $updates['remaining_amount'] = 0;
                $updates['remaining_installments'] = 0;
                $updates['end_date'] = $paidAt->toDateString();
            }

            $loan->update($updates);

            return $payment->refresh();
        });
    }

    /**
     * Early repayment: settle remaining balance and cancel future installments (HR-086).
     */
    public function settleEarly(Loan $loan, DateTimeInterface|string|null $paidDate = null): Loan
    {
        return DB::transaction(function () use ($loan, $paidDate) {
            $loan = Loan::query()->whereKey($loan->getKey())->lockForUpdate()->firstOrFail();

            if ($loan->status !== LoanStatus::Active) {
                throw new InvalidArgumentException('Only active loans can be settled early.');
            }

            $paidAt = Carbon::parse($paidDate ?? now())->startOfDay();
            $remaining = (float) $loan->remaining_amount;

            if ($remaining <= 0) {
                throw new InvalidArgumentException('Loan has no remaining balance to settle.');
            }

            $nextPayment = LoanPayment::query()
                ->where('loan_id', $loan->id)
                ->where('status', LoanPaymentStatus::Pending)
                ->orderBy('installment_number')
                ->lockForUpdate()
                ->first();

            if ($nextPayment !== null) {
                $nextPayment->update([
                    'amount' => $remaining,
                    'status' => LoanPaymentStatus::Paid,
                    'paid_date' => $paidAt->toDateString(),
                ]);
            }

            LoanPayment::query()
                ->where('loan_id', $loan->id)
                ->where('status', LoanPaymentStatus::Pending)
                ->update([
                    'status' => LoanPaymentStatus::Cancelled,
                    'updated_at' => now(),
                ]);

            $loan->update([
                'remaining_amount' => 0,
                'remaining_installments' => 0,
                'status' => LoanStatus::Completed,
                'end_date' => $paidAt->toDateString(),
            ]);

            return $loan->refresh()->load('payments');
        });
    }

    /**
     * Due installments in a payroll period for Phase 8 (HR-087).
     *
     * @return Collection<int, LoanPayment>
     */
    public function deductionsForPeriod(Employee $employee, PayrollPeriod $period): Collection
    {
        $start = Carbon::parse($period->start_date)->startOfDay();
        $end = Carbon::parse($period->end_date)->endOfDay();

        return LoanPayment::query()
            ->whereHas('loan', function ($query) use ($employee) {
                $query->where('employee_id', $employee->id)
                    ->where('status', LoanStatus::Active);
            })
            ->where('status', LoanPaymentStatus::Pending)
            ->whereDate('due_date', '>=', $start->toDateString())
            ->whereDate('due_date', '<=', $end->toDateString())
            ->orderBy('due_date')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: float, 1: int, 2: float, 3: Carbon}
     */
    protected function normalizeApplication(array $data): array
    {
        if (! isset($data['amount'], $data['installments'])) {
            throw new InvalidArgumentException('amount and installments are required.');
        }

        $amount = round((float) $data['amount'], 2);
        $installments = (int) $data['installments'];

        if ($amount <= 0) {
            throw new InvalidArgumentException('Loan amount must be greater than zero.');
        }

        if ($installments <= 0) {
            throw new InvalidArgumentException('Installments must be greater than zero.');
        }

        $installmentAmount = isset($data['installment_amount'])
            ? round((float) $data['installment_amount'], 2)
            : round($amount / $installments, 2);

        $this->assertInstallmentSchedule($amount, $installments, $installmentAmount);

        $startDate = isset($data['start_date'])
            ? Carbon::parse($data['start_date'])->startOfDay()
            : Carbon::now()->startOfDay();

        return [$amount, $installments, $installmentAmount, $startDate];
    }

    protected function assertApplicationLimits(
        Employee $employee,
        float $amount,
        int $installments,
        float $installmentAmount,
        ?Carbon $proposedStartDate = null,
        ?int $excludeLoanId = null,
        bool $checkActiveLimit = true,
    ): void {
        if (! config('hr.loan.enabled', true)) {
            throw new InvalidArgumentException('Loans are disabled in configuration.');
        }

        $maxAmount = config('hr.loan.max_amount');

        if ($maxAmount !== null && $amount > (float) $maxAmount) {
            throw new InvalidArgumentException("Loan amount exceeds the configured maximum of {$maxAmount}.");
        }

        $maxInstallments = (int) config('hr.loan.max_installments', 24);
        $minInstallments = (int) config('hr.loan.min_installments', 1);

        if ($installments > $maxInstallments) {
            throw new InvalidArgumentException("Installments cannot exceed {$maxInstallments}.");
        }

        if ($installments < $minInstallments) {
            throw new InvalidArgumentException("Installments must be at least {$minInstallments}.");
        }

        if ($checkActiveLimit) {
            $this->assertActiveLoanLimit($employee, $excludeLoanId);
        }
        $this->assertLoanCooldown($employee, $proposedStartDate ?? Carbon::now()->startOfDay(), $excludeLoanId);
        $this->assertInstallmentSalaryLimit($employee, $installmentAmount);
        $this->assertInstallmentSchedule($amount, $installments, $installmentAmount);
    }

    protected function assertInstallmentSchedule(float $amount, int $installments, float $installmentAmount): void
    {
        if ($installmentAmount <= 0) {
            throw new InvalidArgumentException('Installment amount must be greater than zero.');
        }

        $lastInstallment = round($amount - ($installmentAmount * ($installments - 1)), 2);

        if ($lastInstallment <= 0) {
            throw new InvalidArgumentException(
                'Loan installment schedule would produce a non-positive final installment.'
            );
        }

        if (round($installmentAmount * $installments, 2) + 0.01 < $amount) {
            throw new InvalidArgumentException(
                'Installment amount is too small to cover the loan principal.'
            );
        }
    }

    /**
     * Apply configured termination policy to outstanding active loans.
     */
    public function handleTermination(Employee $employee): void
    {
        $policy = (string) config('hr.loan.termination_policy', 'mark_receivable');

        $active = Loan::query()
            ->where('employee_id', $employee->id)
            ->where('status', LoanStatus::Active)
            ->lockForUpdate()
            ->get();

        if ($active->isEmpty() || $policy === 'leave_active') {
            return;
        }

        if ($policy === 'require_settlement') {
            throw new InvalidEmployeeLifecycleException(
                'Employee has outstanding active loans that must be settled before termination.'
            );
        }

        foreach ($active as $loan) {
            $loan->update([
                'status' => LoanStatus::Receivable,
                'notes' => trim(($loan->notes ?? '').' [termination: outstanding balance remains receivable]'),
            ]);
        }
    }

    protected function assertActiveLoanLimit(Employee $employee, ?int $excludeLoanId = null): void
    {
        $maxActive = (int) config('hr.loan.max_active_loans', 2);

        $query = Loan::query()
            ->where('employee_id', $employee->id)
            ->where('status', LoanStatus::Active)
            ->lockForUpdate();

        if ($excludeLoanId !== null) {
            $query->whereKeyNot($excludeLoanId);
        }

        if ($query->count() >= $maxActive) {
            throw new InvalidArgumentException(
                "Employee already has the maximum of {$maxActive} active loans."
            );
        }
    }

    protected function assertLoanCooldown(Employee $employee, Carbon $proposedStartDate, ?int $excludeLoanId = null): void
    {
        $minMonths = (int) config('hr.loan.min_months_between_loans', 0);

        if ($minMonths <= 0) {
            return;
        }

        $query = Loan::query()
            ->where('employee_id', $employee->id)
            ->whereIn('status', [LoanStatus::Active, LoanStatus::Completed])
            ->orderByDesc('start_date');

        if ($excludeLoanId !== null) {
            $query->whereKeyNot($excludeLoanId);
        }

        $recent = $query->first();

        if ($recent === null) {
            return;
        }

        $eligibleFrom = Carbon::parse($recent->start_date)->startOfDay()->addMonths($minMonths);

        if ($proposedStartDate->copy()->startOfDay()->lt($eligibleFrom)) {
            throw new InvalidArgumentException(
                "A new loan cannot start until {$eligibleFrom->toDateString()} ({$minMonths} months after the previous loan start date)."
            );
        }
    }

    protected function assertInstallmentSalaryLimit(Employee $employee, float $installmentAmount): void
    {
        $maxPercent = config('hr.loan.max_percentage_of_salary');

        if ($maxPercent === null) {
            return;
        }

        $salary = $this->salaries->currentSalary($employee);

        if ($salary === null) {
            throw new InvalidArgumentException('Employee has no current salary to validate installment against.');
        }

        $baseSalary = (float) $salary->base_salary;
        $maxInstallment = round($baseSalary * ((float) $maxPercent / 100), 2);

        if ($installmentAmount > $maxInstallment) {
            throw new InvalidArgumentException(
                "Installment amount exceeds {$maxPercent}% of base salary (max {$maxInstallment})."
            );
        }
    }

    protected function generatePaymentSchedule(Loan $loan): void
    {
        if ($loan->payments()->exists()) {
            throw new InvalidArgumentException('Loan already has an installment schedule.');
        }

        $startDate = Carbon::parse($loan->start_date)->startOfDay();
        $installments = (int) $loan->installments;
        $totalAmount = (float) $loan->amount;
        $regularInstallment = round((float) $loan->installment_amount, 2);
        $lastDueDate = $startDate->copy();

        for ($i = 1; $i <= $installments; $i++) {
            $dueDate = $startDate->copy()->addMonths($i - 1);
            $lastDueDate = $dueDate;

            $paymentAmount = $i === $installments
                ? round($totalAmount - ($regularInstallment * ($installments - 1)), 2)
                : $regularInstallment;

            if ($paymentAmount <= 0) {
                throw new InvalidArgumentException(
                    'Loan installment schedule would produce a non-positive installment.'
                );
            }

            LoanPayment::query()->create([
                'loan_id' => $loan->id,
                'installment_number' => $i,
                'amount' => $paymentAmount,
                'due_date' => $dueDate->toDateString(),
                'status' => LoanPaymentStatus::Pending,
            ]);
        }

        $loan->update(['end_date' => $lastDueDate->toDateString()]);
    }

    protected function generateLoanNumber(Carbon $startDate): string
    {
        $year = $startDate->year;
        $sequence = $this->sequences->nextValue("loan:{$year}");

        return sprintf('LN-%d-%04d', $year, $sequence);
    }
}
