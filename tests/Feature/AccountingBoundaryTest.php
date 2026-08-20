<?php

namespace Karnoweb\Hr\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Karnoweb\Hr\Enums\PayrollPeriodStatus;
use Karnoweb\Hr\Events\LoanDisbursed;
use Karnoweb\Hr\Events\PayrollPeriodApproved;
use Karnoweb\Hr\Events\PayrollPeriodPaid;
use Karnoweb\Hr\Facades\Hr;
use Karnoweb\Hr\Tests\TestCase;

class AccountingBoundaryTest extends TestCase
{
    public function test_payroll_period_approved_event_fires_once_with_payload(): void
    {
        Event::fake([PayrollPeriodApproved::class]);

        $employee = $this->employeeWithSalary();
        $period = Hr::payroll()->openPeriod(1, 2026, 6);
        Hr::payroll()->calculate($period);
        Hr::payroll()->approve($period->fresh(), approvedBy: 42);

        Event::assertDispatched(PayrollPeriodApproved::class, function (PayrollPeriodApproved $event) use ($period, $employee) {
            $this->assertSame($period->id, $event->period->id);
            $this->assertSame(42, $event->approvedBy);
            $this->assertArrayHasKey('payable', $event->periodTotals);
            $this->assertNotEmpty($event->employees);

            $line = collect($event->employees)->firstWhere('employee_id', $employee->id);
            $this->assertNotNull($line);
            $this->assertArrayHasKey('compensation', $line);
            $this->assertArrayHasKey('employee_liabilities', $line);
            $this->assertArrayHasKey('employer_liabilities', $line);
            $this->assertArrayHasKey('loan_deductions', $line);

            return true;
        });

        Event::assertDispatchedTimes(PayrollPeriodApproved::class, 1);
    }

    public function test_payroll_period_paid_event_fires_after_mark_paid(): void
    {
        Event::fake([PayrollPeriodPaid::class]);

        $this->employeeWithSalary();
        $period = Hr::payroll()->openPeriod(1, 2026, 7);
        Hr::payroll()->calculate($period);
        Hr::payroll()->approve($period->fresh());
        Hr::payroll()->markPaid($period->fresh());

        Event::assertDispatched(PayrollPeriodPaid::class, function (PayrollPeriodPaid $event) use ($period) {
            return $event->period->id === $period->id
                && $event->period->status === PayrollPeriodStatus::Paid;
        });
    }

    public function test_loan_disbursed_event_fires_on_approve(): void
    {
        Event::fake([LoanDisbursed::class]);

        $employee = $this->employeeWithSalary();
        $loan = Hr::loans()->apply($employee, [
            'loan_number' => 'LN-ACC-1',
            'amount' => 10_000_000,
            'installments' => 10,
            'start_date' => '2026-04-01',
        ]);

        Hr::loans()->approve($loan);

        Event::assertDispatched(LoanDisbursed::class, function (LoanDisbursed $event) use ($employee, $loan) {
            return $event->loan->id === $loan->id
                && $event->employeeId === $employee->id
                && $event->amount === 10_000_000.0
                && $event->disbursedAt === '2026-04-01';
        });
    }

    public function test_approved_event_includes_loan_deduction_detail(): void
    {
        Event::fake([PayrollPeriodApproved::class]);

        $employee = $this->employeeWithSalary();
        $loan = Hr::loans()->approve(Hr::loans()->apply($employee, [
            'loan_number' => 'LN-ACC-2',
            'amount' => 5_000_000,
            'installments' => 5,
            'start_date' => '2026-01-01',
        ]));

        $period = Hr::payroll()->openPeriod(1, 2026, 3);
        Hr::payroll()->calculate($period);
        Hr::payroll()->approve($period->fresh());

        Event::assertDispatched(PayrollPeriodApproved::class, function (PayrollPeriodApproved $event) use ($employee, $loan) {
            $line = collect($event->employees)->firstWhere('employee_id', $employee->id);
            $this->assertNotEmpty($line['loan_deductions']);
            $this->assertSame($loan->id, $line['loan_deductions'][0]['loan_id']);

            return true;
        });
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
}
