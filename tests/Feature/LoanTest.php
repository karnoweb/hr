<?php

namespace Karnoweb\Hr\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Database\QueryException;
use InvalidArgumentException;
use Karnoweb\Hr\Enums\LoanPaymentStatus;
use Karnoweb\Hr\Enums\LoanStatus;
use Karnoweb\Hr\Enums\PayrollPeriodStatus;
use Karnoweb\Hr\Facades\Hr;
use Karnoweb\Hr\Models\Loan;
use Karnoweb\Hr\Models\LoanPayment;
use Karnoweb\Hr\Models\PayrollPeriod;
use Karnoweb\Hr\Services\LoanService;
use Karnoweb\Hr\Tests\TestCase;

class LoanTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'hr.loan.enabled' => true,
            'hr.loan.max_amount' => 100_000_000,
            'hr.loan.max_installments' => 24,
            'hr.loan.min_installments' => 1,
            'hr.loan.min_months_between_loans' => 6,
            'hr.loan.max_active_loans' => 2,
            'hr.loan.max_percentage_of_salary' => 50,
        ]);
    }

    public function test_apply_and_approve_generates_installment_schedule(): void
    {
        $employee = $this->employeeWithSalary();

        $loan = Hr::loans()->apply($employee, [
            'amount' => 24_000_000,
            'installments' => 12,
            'start_date' => '2026-03-01',
        ]);

        $this->assertSame(LoanStatus::Pending, $loan->status);
        $this->assertSame(0, $loan->payments()->count());

        $approved = Hr::loans()->approve($loan);

        $this->assertSame(LoanStatus::Active, $approved->status);
        $this->assertSame(12, $approved->payments()->count());
        $this->assertSame('2026-03-01', $approved->payments()->orderBy('installment_number')->first()->due_date->toDateString());
        $this->assertSame('2027-02-01', $approved->payments()->orderByDesc('installment_number')->first()->due_date->toDateString());
        $this->assertEqualsWithDelta(24_000_000.0, (float) $approved->payments()->sum('amount'), 0.01);
    }

    public function test_apply_rejects_amount_above_max(): void
    {
        config(['hr.loan.max_amount' => 10_000_000]);

        $employee = $this->employeeWithSalary();

        $this->expectException(InvalidArgumentException::class);
        Hr::loans()->apply($employee, [
            'amount' => 20_000_000,
            'installments' => 10,
        ]);
    }

    public function test_apply_rejects_installment_above_salary_percentage(): void
    {
        $employee = $this->employeeWithSalary(50_000_000);

        $this->expectException(InvalidArgumentException::class);
        Hr::loans()->apply($employee, [
            'amount' => 60_000_000,
            'installments' => 2,
            'installment_amount' => 30_000_000,
        ]);
    }

    public function test_apply_rejects_within_min_months_between_loans(): void
    {
        $employee = $this->employeeWithSalary();

        Loan::query()->create([
            'employee_id' => $employee->id,
            'loan_number' => 'LN-PREV-1',
            'amount' => 10_000_000,
            'installments' => 10,
            'installment_amount' => 1_000_000,
            'remaining_amount' => 0,
            'remaining_installments' => 0,
            'start_date' => '2026-01-01',
            'status' => LoanStatus::Completed,
        ]);

        $this->expectException(InvalidArgumentException::class);
        Hr::loans()->apply($employee, [
            'amount' => 5_000_000,
            'installments' => 5,
            'start_date' => '2026-04-01',
        ]);
    }

    public function test_approve_rejects_when_max_active_loans_reached(): void
    {
        $employee = $this->employeeWithSalary();

        $first = Hr::loans()->apply($employee, [
            'loan_number' => 'LN-A',
            'amount' => 5_000_000,
            'installments' => 5,
            'start_date' => '2026-01-01',
        ]);
        Hr::loans()->approve($first);

        $second = Hr::loans()->apply($employee, [
            'loan_number' => 'LN-B',
            'amount' => 5_000_000,
            'installments' => 5,
            'start_date' => '2026-07-01',
        ]);
        Hr::loans()->approve($second);

        $third = Hr::loans()->apply($employee, [
            'loan_number' => 'LN-C',
            'amount' => 5_000_000,
            'installments' => 5,
            'start_date' => '2027-01-01',
        ]);

        $this->expectException(InvalidArgumentException::class);
        Hr::loans()->approve($third);
    }

    public function test_record_payment_keeps_loan_remaining_fields_in_sync(): void
    {
        $employee = $this->employeeWithSalary();

        $loan = Hr::loans()->approve(Hr::loans()->apply($employee, [
            'amount' => 12_000_000,
            'installments' => 12,
            'start_date' => '2026-01-01',
        ]));

        $payment = $loan->payments()->orderBy('installment_number')->first();
        Hr::loans()->recordPayment($payment, '2026-01-15');

        $loan->refresh();

        $this->assertSame(LoanPaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame('11000000.00', $loan->remaining_amount);
        $this->assertSame(11, $loan->remaining_installments);
    }

    public function test_settle_early_completes_loan_and_cancels_future_installments(): void
    {
        $employee = $this->employeeWithSalary();

        $loan = Hr::loans()->approve(Hr::loans()->apply($employee, [
            'amount' => 12_000_000,
            'installments' => 12,
            'start_date' => '2026-01-01',
        ]));

        Hr::loans()->recordPayment($loan->payments()->orderBy('installment_number')->first(), '2026-01-15');

        $settled = Hr::loans()->settleEarly($loan->fresh(), '2026-02-01');

        $this->assertSame(LoanStatus::Completed, $settled->status);
        $this->assertSame('0.00', $settled->remaining_amount);
        $this->assertSame(0, $settled->remaining_installments);
        $this->assertSame(
            10,
            LoanPayment::query()->where('loan_id', $loan->id)->where('status', LoanPaymentStatus::Cancelled)->count()
        );
        $this->assertSame(2, LoanPayment::query()->where('loan_id', $loan->id)->where('status', LoanPaymentStatus::Paid)->count());
    }

    public function test_deductions_for_period_returns_due_installments(): void
    {
        $employee = $this->employeeWithSalary();

        $loan = Hr::loans()->approve(Hr::loans()->apply($employee, [
            'amount' => 12_000_000,
            'installments' => 12,
            'start_date' => '2026-01-01',
        ]));

        $period = PayrollPeriod::query()->create([
            'year' => 2026,
            'month' => 3,
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-31',
            'working_days' => 22,
            'status' => PayrollPeriodStatus::Draft,
        ]);

        $deductions = Hr::loans()->deductionsForPeriod($employee, $period);

        $this->assertCount(1, $deductions);
        $this->assertSame($loan->id, $deductions->first()->loan_id);
        $this->assertSame('2026-03-01', $deductions->first()->due_date->toDateString());
    }

    public function test_installment_number_unique_constraint_at_database_level(): void
    {
        $employee = $this->employeeWithSalary();

        $loan = Hr::loans()->approve(Hr::loans()->apply($employee, [
            'amount' => 5_000_000,
            'installments' => 5,
            'start_date' => '2026-01-01',
        ]));

        $this->expectException(QueryException::class);

        LoanPayment::query()->create([
            'loan_id' => $loan->id,
            'installment_number' => 1,
            'amount' => 1_000_000,
            'due_date' => '2026-05-01',
            'status' => LoanPaymentStatus::Pending,
        ]);
    }

    public function test_apply_rejects_installment_amount_that_cannot_cover_principal(): void
    {
        $employee = $this->employeeWithSalary();

        $this->expectException(InvalidArgumentException::class);
        Hr::loans()->apply($employee, [
            'amount' => 10_000_000,
            'installments' => 3,
            'installment_amount' => 6_000_000,
        ]);
    }

    public function test_loan_service_is_container_singleton(): void
    {
        $this->assertSame(
            $this->app->make(LoanService::class),
            Hr::loans()
        );
    }

    protected function employeeWithSalary(float $baseSalary = 50_000_000)
    {
        $employee = Hr::employees()->createForUser($this->makeUser(), ['branch_id' => 1]);
        Hr::salaries()->assign($employee, [
            'base_salary' => $baseSalary,
            'effective_date' => '2026-01-01',
        ]);

        return $employee->fresh();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
