<?php

namespace Karnoweb\Hr\Tests\Feature;

use Carbon\Carbon;
use Karnoweb\Hr\Enums\AttendanceStatus;
use Karnoweb\Hr\Enums\LeaveRequestStatus;
use Karnoweb\Hr\Enums\LoanPaymentStatus;
use Karnoweb\Hr\Enums\LoanStatus;
use Karnoweb\Hr\Enums\OvertimeStatus;
use Karnoweb\Hr\Enums\OvertimeType;
use Karnoweb\Hr\Enums\PayrollPeriodStatus;
use Karnoweb\Hr\Enums\PayrollRecordStatus;
use Karnoweb\Hr\Exceptions\PayrollPeriodExistsException;
use Karnoweb\Hr\Exceptions\PayrollPeriodLockedException;
use Karnoweb\Hr\Facades\Hr;
use Karnoweb\Hr\Models\AttendanceRecord;
use Karnoweb\Hr\Models\LeaveRequest;
use Karnoweb\Hr\Models\Loan;
use Karnoweb\Hr\Models\LoanPayment;
use Karnoweb\Hr\Models\OvertimeRecord;
use Karnoweb\Hr\Models\PayrollRecord;
use Karnoweb\Hr\Models\Shift;
use Karnoweb\Hr\Services\PayrollService;
use Karnoweb\Hr\Tests\TestCase;

class PayrollTest extends TestCase
{
    public function test_open_period_computes_working_days(): void
    {
        $period = Hr::payroll()->openPeriod(1, 2026, 3);

        $this->assertSame(2026, $period->year);
        $this->assertSame(3, $period->month);
        $this->assertSame(PayrollPeriodStatus::Draft, $period->status);
        $this->assertGreaterThan(0, $period->working_days);
    }

    public function test_open_period_duplicate_throws_domain_exception(): void
    {
        Hr::payroll()->openPeriod(1, 2026, 4);

        $this->expectException(PayrollPeriodExistsException::class);
        Hr::payroll()->openPeriod(1, 2026, 4);
    }

    public function test_calculate_is_idempotent_per_employee(): void
    {
        $employee = $this->employeeWithSalary();
        $period = Hr::payroll()->openPeriod(1, 2026, 3);

        AttendanceRecord::query()->create([
            'employee_id' => $employee->id,
            'date' => '2026-03-02',
            'status' => AttendanceStatus::Present,
            'late_minutes' => 15,
            'source' => 'manual',
        ]);

        Hr::payroll()->calculate($period);
        Hr::payroll()->calculate($period->fresh());

        $this->assertSame(1, PayrollRecord::query()->where('payroll_period_id', $period->id)->count());
    }

    public function test_golden_master_pipeline_populates_payroll_record(): void
    {
        $employee = $this->employeeWithSalary(60_000_000);

        AttendanceRecord::query()->create([
            'employee_id' => $employee->id,
            'date' => '2026-03-02',
            'status' => AttendanceStatus::Present,
            'late_minutes' => 10,
            'early_leave_minutes' => 5,
            'source' => 'manual',
        ]);

        AttendanceRecord::query()->create([
            'employee_id' => $employee->id,
            'date' => '2026-03-03',
            'status' => AttendanceStatus::Absent,
            'source' => 'manual',
        ]);

        LeaveRequest::query()->create([
            'employee_id' => $employee->id,
            'type' => 'annual',
            'start_date' => '2026-03-04',
            'end_date' => '2026-03-05',
            'days' => 2,
            'status' => LeaveRequestStatus::Approved,
        ]);

        OvertimeRecord::query()->create([
            'employee_id' => $employee->id,
            'date' => '2026-03-06',
            'calculated_minutes' => 120,
            'approved_minutes' => 120,
            'type' => OvertimeType::Regular,
            'status' => OvertimeStatus::Approved,
        ]);

        $loan = Loan::query()->create([
            'employee_id' => $employee->id,
            'loan_number' => 'LN-PAY-1',
            'amount' => 12_000_000,
            'installments' => 12,
            'installment_amount' => 1_000_000,
            'remaining_amount' => 12_000_000,
            'remaining_installments' => 12,
            'start_date' => '2026-01-01',
            'status' => LoanStatus::Active,
        ]);

        LoanPayment::query()->create([
            'loan_id' => $loan->id,
            'installment_number' => 3,
            'amount' => 1_000_000,
            'due_date' => '2026-03-01',
            'status' => LoanPaymentStatus::Pending,
        ]);

        $period = Hr::payroll()->openPeriod(1, 2026, 3);
        Hr::payroll()->calculate($period);

        $record = PayrollRecord::query()
            ->where('payroll_period_id', $period->id)
            ->where('employee_id', $employee->id)
            ->firstOrFail();

        $this->assertSame(1, $record->present_days);
        $this->assertSame(1, $record->absent_days);
        $this->assertSame(10, $record->late_minutes);
        $this->assertSame(2.0, (float) $record->leave_days_paid);
        $this->assertSame(120, $record->overtime_minutes);
        $this->assertEqualsWithDelta(60_000_000.0, (float) $record->base_salary, 0.01);
        $this->assertEqualsWithDelta(1_000_000.0, (float) $record->loan_deduction, 0.01);
        $this->assertGreaterThan(0, (float) $record->insurance_employee);
        $this->assertGreaterThan(0, (float) $record->tax);
        $this->assertEqualsWithDelta(
            (float) $record->gross_salary - (float) $record->insurance_employee - (float) $record->tax - (float) $record->loan_deduction,
            (float) $record->net_salary,
            0.02
        );
    }

    public function test_approve_commits_loan_deductions_and_locks_recalculation(): void
    {
        $employee = $this->employeeWithSalary();
        $period = Hr::payroll()->openPeriod(1, 2026, 3);

        $loan = Hr::loans()->approve(Hr::loans()->apply($employee, [
            'loan_number' => 'LN-PAY-2',
            'amount' => 6_000_000,
            'installments' => 6,
            'start_date' => '2026-01-01',
        ]));

        $payment = $loan->payments()->whereDate('due_date', '2026-03-01')->first();
        $this->assertNotNull($payment);

        Hr::payroll()->calculate($period);
        Hr::payroll()->approve($period->fresh(), approvedBy: 99);

        $payment->refresh();
        $this->assertSame(LoanPaymentStatus::Paid, $payment->status);
        $this->assertNotNull($payment->payroll_record_id);

        $this->expectException(PayrollPeriodLockedException::class);
        Hr::payroll()->calculate($period->fresh());
    }

    public function test_mark_paid_requires_approved_period(): void
    {
        $employee = $this->employeeWithSalary();
        $period = Hr::payroll()->openPeriod(1, 2026, 5);
        Hr::payroll()->calculate($period);
        Hr::payroll()->approve($period->fresh());

        $paid = Hr::payroll()->markPaid($period->fresh());

        $this->assertSame(PayrollPeriodStatus::Paid, $paid->status);
        $this->assertNotNull($paid->paid_at);
        $this->assertSame(
            PayrollRecordStatus::Paid,
            PayrollRecord::query()->where('payroll_period_id', $period->id)->first()->status
        );
    }

    public function test_mid_period_salary_change_is_prorated(): void
    {
        $employee = $this->employeeWithSalary(50_000_000);

        Hr::salaries()->changeSalary($employee, [
            'base_salary' => 70_000_000,
            'effective_date' => '2026-03-15',
        ]);

        $period = Hr::payroll()->openPeriod(1, 2026, 3);
        Hr::payroll()->calculate($period);

        $record = PayrollRecord::query()
            ->where('payroll_period_id', $period->id)
            ->where('employee_id', $employee->id)
            ->firstOrFail();

        $expected = round((50_000_000 * 14 / 31) + (70_000_000 * 17 / 31), 2);

        $this->assertEqualsWithDelta($expected, (float) $record->base_salary, 0.01);
        $this->assertCount(2, $record->calculation_log['salary_segments'] ?? []);
        $this->assertSame('calendar_days', $record->calculation_log['policy']['salary_proration']);
        $this->assertArrayHasKey('insurance_rate_id', $record->calculation_log['insurance']);
        $this->assertArrayHasKey('tax_bracket_id', $record->calculation_log['tax']);
    }

    public function test_cross_period_leave_is_allocated_to_overlap_only(): void
    {
        $employee = $this->employeeWithSalary();

        LeaveRequest::query()->create([
            'employee_id' => $employee->id,
            'type' => 'annual',
            'start_date' => '2026-02-26',
            'end_date' => '2026-03-03',
            'days' => 6,
            'status' => LeaveRequestStatus::Approved,
        ]);

        $period = Hr::payroll()->openPeriod(1, 2026, 3);
        Hr::payroll()->calculate($period);

        $record = PayrollRecord::query()
            ->where('payroll_period_id', $period->id)
            ->where('employee_id', $employee->id)
            ->firstOrFail();

        $this->assertLessThan(6, (float) $record->leave_days_paid);
        $this->assertGreaterThan(0, (float) $record->leave_days_paid);
        $this->assertNotEmpty($record->calculation_log['leave_allocation'] ?? []);
    }

    public function test_payroll_service_is_container_singleton(): void
    {
        $this->assertSame(
            $this->app->make(PayrollService::class),
            Hr::payroll()
        );
    }

    protected function employeeWithSalary(float $baseSalary = 50_000_000)
    {
        $employee = Hr::employees()->createForUser($this->makeUser(), ['branch_id' => 1]);

        $shift = Shift::query()->create([
            'branch_id' => 1,
            'code' => 'PAY-DAY',
            'name' => 'Day',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'work_minutes' => 480,
        ]);

        Hr::shiftAssignments()->assignShift($employee, $shift->id, '2026-01-01');

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
