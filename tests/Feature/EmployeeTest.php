<?php

namespace Karnoweb\Hr\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Karnoweb\Hr\Enums\ApprovalStatus;
use Karnoweb\Hr\Enums\ApproverType;
use Karnoweb\Hr\Enums\ContractStatus;
use Karnoweb\Hr\Enums\ContractType;
use Karnoweb\Hr\Enums\DocumentStatus;
use Karnoweb\Hr\Enums\DocumentType;
use Karnoweb\Hr\Enums\EmployeeStatus;
use Karnoweb\Hr\Enums\LeaveRequestStatus;
use Karnoweb\Hr\Enums\LoanStatus;
use Karnoweb\Hr\Exceptions\EmployeeAlreadyExistsException;
use Karnoweb\Hr\Exceptions\InvalidEmployeeLifecycleException;
use Karnoweb\Hr\Facades\Hr;
use Karnoweb\Hr\Models\Contract;
use Karnoweb\Hr\Models\Department;
use Karnoweb\Hr\Models\DocumentApproval;
use Karnoweb\Hr\Models\Employee;
use Karnoweb\Hr\Models\EmployeePosition;
use Karnoweb\Hr\Models\EmployeeSalary;
use Karnoweb\Hr\Models\HrDocument;
use Karnoweb\Hr\Models\LeaveRequest;
use Karnoweb\Hr\Models\Loan;
use Karnoweb\Hr\Models\MissionRequest;
use Karnoweb\Hr\Models\Position;
use Karnoweb\Hr\Models\Workflow;
use Karnoweb\Hr\Models\WorkflowStep;
use Karnoweb\Hr\Services\EmployeeService;
use Karnoweb\Hr\Tests\Fixtures\User;
use Karnoweb\Hr\Tests\TestCase;

class EmployeeTest extends TestCase
{
    public function test_create_for_user_happy_path_generates_code(): void
    {
        Carbon::setTestNow('2026-03-15 12:00:00');

        $user = $this->makeUser();
        $employee = Hr::employees()->createForUser($user, [
            'branch_id' => 1,
            'national_id' => '0123456789',
        ]);

        $this->assertInstanceOf(Employee::class, $employee);
        $this->assertSame(User::class, $employee->employable_type);
        $this->assertEquals($user->id, $employee->employable_id);
        $this->assertSame(EmployeeStatus::Active, $employee->status);
        $this->assertSame('2026-0001', $employee->employee_code);
        $this->assertTrue($employee->isActive());
        $this->assertNotNull(Hr::employees()->findByUser($user));
    }

    public function test_create_for_user_rejects_wrong_user_class(): void
    {
        $other = new class extends Model
        {
            protected $table = 'users';

            protected $guarded = [];
        };

        $this->expectException(InvalidArgumentException::class);
        Hr::employees()->createForUser($other);
    }

    public function test_create_for_user_rejects_duplicate_employable(): void
    {
        $user = $this->makeUser();
        Hr::employees()->createForUser($user);

        $this->expectException(EmployeeAlreadyExistsException::class);
        Hr::employees()->createForUser($user);
    }

    public function test_national_id_unique_constraint(): void
    {
        Hr::employees()->createForUser($this->makeUser(), ['national_id' => '0123456789']);

        $this->expectException(EmployeeAlreadyExistsException::class);
        Hr::employees()->createForUser($this->makeUser(), ['national_id' => '0123456789']);
    }

    public function test_multiple_null_national_ids_are_allowed(): void
    {
        $a = Hr::employees()->createForUser($this->makeUser(), ['national_id' => null]);
        $b = Hr::employees()->createForUser($this->makeUser(), ['national_id' => null]);

        $this->assertNull($a->national_id);
        $this->assertNull($b->national_id);
        $this->assertNotSame($a->id, $b->id);
    }

    public function test_employee_code_sequence_is_gapless_and_unique(): void
    {
        Carbon::setTestNow('2026-01-01');

        $codes = [];
        for ($i = 0; $i < 20; $i++) {
            $codes[] = Hr::employees()->createForUser($this->makeUser())->employee_code;
        }

        $this->assertSame(
            array_map(fn (int $n) => '2026-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT), range(1, 20)),
            $codes
        );
        $this->assertCount(20, array_unique($codes));
    }

    public function test_sequence_per_branch_requires_branch_placeholder(): void
    {
        config([
            'hr.employee_code.sequence_per_branch' => true,
            'hr.employee_code.format' => '{year}-{sequence}',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('{branch}');

        Hr::employees()->generateEmployeeCode(1);
    }

    public function test_sequence_per_branch_with_placeholder_avoids_cross_branch_collision(): void
    {
        Carbon::setTestNow('2026-01-01');
        config([
            'hr.employee_code.sequence_per_branch' => true,
            'hr.employee_code.format' => '{year}-{branch}-{sequence}',
        ]);

        $a = Hr::employees()->createForUser($this->makeUser(), ['branch_id' => 1]);
        $b = Hr::employees()->createForUser($this->makeUser(), ['branch_id' => 2]);

        $this->assertSame('2026-1-0001', $a->employee_code);
        $this->assertSame('2026-2-0001', $b->employee_code);
        $this->assertNotSame($a->employee_code, $b->employee_code);
    }

    public function test_suspend_does_not_close_related_records(): void
    {
        $employee = $this->seedActiveEmployeeWithRelations();

        Hr::employees()->suspend($employee);

        $employee->refresh();
        $this->assertSame(EmployeeStatus::Suspended, $employee->status);
        $this->assertNull($employee->termination_date);

        $this->assertSame(ContractStatus::Active, Contract::query()->where('employee_id', $employee->id)->first()->status);
        $this->assertNull(EmployeePosition::query()->where('employee_id', $employee->id)->first()->end_date);
        $this->assertTrue(EmployeeSalary::query()->where('employee_id', $employee->id)->first()->is_current);
    }

    public function test_terminate_side_effects_are_atomic_and_complete(): void
    {
        $employee = $this->seedActiveEmployeeWithRelations();
        $terminationDate = Carbon::parse('2026-06-01');

        Hr::employees()->terminate($employee, $terminationDate);

        $employee->refresh();
        $this->assertSame(EmployeeStatus::Terminated, $employee->status);
        $this->assertTrue($employee->termination_date->equalTo($terminationDate->startOfDay()));

        $contract = Contract::query()->where('employee_id', $employee->id)->first();
        $this->assertSame(ContractStatus::Terminated, $contract->status);
        $this->assertTrue($contract->end_date->equalTo($terminationDate->startOfDay()));

        $position = EmployeePosition::query()->where('employee_id', $employee->id)->first();
        $this->assertTrue($position->end_date->equalTo($terminationDate->startOfDay()));

        $salary = EmployeeSalary::query()->where('employee_id', $employee->id)->first();
        $this->assertFalse($salary->is_current);
        $this->assertTrue($salary->end_date->equalTo($terminationDate->startOfDay()));

        $this->assertSame(
            LeaveRequestStatus::Cancelled,
            LeaveRequest::query()->where('employee_id', $employee->id)->first()->status
        );
        $this->assertSame(
            LeaveRequestStatus::Cancelled,
            MissionRequest::query()->where('employee_id', $employee->id)->first()->status
        );
        $this->assertSame(
            LoanStatus::Cancelled,
            Loan::query()->where('employee_id', $employee->id)->where('loan_number', 'LN-PENDING')->first()->status
        );
        $this->assertSame(
            LoanStatus::Active,
            Loan::query()->where('employee_id', $employee->id)->where('loan_number', 'LN-ACTIVE')->first()->status
        );
        $this->assertSame(
            ApprovalStatus::Skipped,
            DocumentApproval::query()->where('assigned_to', $employee->employable_id)->first()->status
        );
    }

    public function test_terminate_rolls_back_when_side_effect_fails(): void
    {
        $employee = Hr::employees()->createForUser($this->makeUser());

        LeaveRequest::query()->create([
            'employee_id' => $employee->id,
            'type' => 'annual',
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'days' => 1,
            'status' => LeaveRequestStatus::Pending,
        ]);

        $shouldFail = true;
        DB::listen(function ($query) use (&$shouldFail) {
            if (
                $shouldFail
                && str_contains($query->sql, 'leave_requests')
                && str_contains(strtolower($query->sql), 'update')
            ) {
                $shouldFail = false;
                throw new \RuntimeException('forced leave update failure');
            }
        });

        try {
            Hr::employees()->terminate($employee);
            $this->fail('Expected terminate to fail');
        } catch (\RuntimeException $e) {
            $this->assertSame('forced leave update failure', $e->getMessage());
        }

        $employee->refresh();
        $this->assertSame(EmployeeStatus::Active, $employee->status);
        $this->assertNull($employee->termination_date);
        $this->assertSame(
            LeaveRequestStatus::Pending,
            LeaveRequest::query()->where('employee_id', $employee->id)->first()->status
        );
    }

    public function test_reactivate_keeps_employee_code_same_year_and_cross_year(): void
    {
        Carbon::setTestNow('2026-01-10');
        $employee = Hr::employees()->createForUser($this->makeUser());
        $code = $employee->employee_code;

        Hr::employees()->terminate($employee);
        $reactivated = Hr::employees()->reactivate($employee->fresh());

        $this->assertSame(EmployeeStatus::Active, $reactivated->status);
        $this->assertNull($reactivated->termination_date);
        $this->assertSame($code, $reactivated->employee_code);

        Hr::employees()->terminate($reactivated);
        Carbon::setTestNow('2028-05-01');
        $again = Hr::employees()->reactivate($reactivated->fresh());

        $this->assertSame($code, $again->employee_code);
        $this->assertSame(EmployeeStatus::Active, $again->status);
    }

    public function test_hire_date_after_termination_date_is_rejected(): void
    {
        $employee = Hr::employees()->createForUser($this->makeUser(), [
            'hire_date' => '2026-06-01',
        ]);

        $this->expectException(InvalidEmployeeLifecycleException::class);
        Hr::employees()->terminate($employee, '2026-01-01');
    }

    public function test_direct_status_update_is_blocked(): void
    {
        $employee = Hr::employees()->createForUser($this->makeUser());

        $this->expectException(InvalidEmployeeLifecycleException::class);
        $employee->update(['status' => EmployeeStatus::Suspended]);
    }

    public function test_employee_service_is_container_singleton_with_sequence_generator(): void
    {
        $this->assertSame(
            $this->app->make(EmployeeService::class),
            Hr::employees()
        );
    }

    protected function seedActiveEmployeeWithRelations(): Employee
    {
        Carbon::setTestNow('2026-01-15');

        $employee = Hr::employees()->createForUser($this->makeUser(), ['branch_id' => 1]);

        $department = Department::query()->create([
            'code' => 'DEP-'.$employee->id,
            'name' => 'Engineering',
        ]);
        $position = Position::query()->create([
            'code' => 'POS-'.$employee->id,
            'name' => 'Developer',
        ]);

        Hr::employees()->assignPosition($employee, $department->id, $position->id);

        Contract::query()->create([
            'employee_id' => $employee->id,
            'contract_number' => 'C-'.$employee->id,
            'type' => ContractType::Permanent,
            'start_date' => now()->toDateString(),
            'status' => ContractStatus::Active,
        ]);

        EmployeeSalary::query()->create([
            'employee_id' => $employee->id,
            'base_salary' => 10000000,
            'effective_date' => now()->toDateString(),
            'is_current' => true,
        ]);

        LeaveRequest::query()->create([
            'employee_id' => $employee->id,
            'type' => 'annual',
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'days' => 1,
            'status' => LeaveRequestStatus::Pending,
        ]);

        MissionRequest::query()->create([
            'employee_id' => $employee->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'destination' => 'Tehran',
            'purpose' => 'Meeting',
            'days' => 1,
            'status' => LeaveRequestStatus::Pending,
        ]);

        Loan::query()->create([
            'employee_id' => $employee->id,
            'loan_number' => 'LN-PENDING',
            'amount' => 1000,
            'installments' => 2,
            'installment_amount' => 500,
            'remaining_amount' => 1000,
            'remaining_installments' => 2,
            'start_date' => now()->toDateString(),
            'status' => LoanStatus::Pending,
        ]);

        Loan::query()->create([
            'employee_id' => $employee->id,
            'loan_number' => 'LN-ACTIVE',
            'amount' => 2000,
            'installments' => 2,
            'installment_amount' => 1000,
            'remaining_amount' => 2000,
            'remaining_installments' => 2,
            'start_date' => now()->toDateString(),
            'status' => LoanStatus::Active,
        ]);

        $document = HrDocument::query()->create([
            'employee_id' => $employee->id,
            'type' => DocumentType::Leave,
            'effective_date' => now()->toDateString(),
            'status' => DocumentStatus::Pending,
        ]);

        $workflow = Workflow::query()->create([
            'name' => 'Leave approval',
            'document_type' => DocumentType::Leave->value,
            'is_active' => true,
            'priority' => 1,
        ]);

        $step = WorkflowStep::query()->create([
            'workflow_id' => $workflow->id,
            'order' => 1,
            'name' => 'Manager',
            'approver_type' => ApproverType::User,
            'approver_id' => $employee->employable_id,
        ]);

        DocumentApproval::query()->create([
            'hr_document_id' => $document->id,
            'workflow_step_id' => $step->id,
            'assigned_to' => $employee->employable_id,
            'status' => ApprovalStatus::Pending,
        ]);

        return $employee->fresh();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
