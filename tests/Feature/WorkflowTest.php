<?php

namespace Karnoweb\Hr\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Karnoweb\Hr\Enums\ApprovalStatus;
use Karnoweb\Hr\Enums\ApproverType;
use Karnoweb\Hr\Enums\DocumentStatus;
use Karnoweb\Hr\Enums\DocumentType;
use Karnoweb\Hr\Enums\TimeoutAction;
use Karnoweb\Hr\Enums\WorkflowExecutionMode;
use Karnoweb\Hr\Exceptions\UnresolvableApproverException;
use Karnoweb\Hr\Facades\Hr;
use Karnoweb\Hr\Models\Department;
use Karnoweb\Hr\Models\Employee;
use Karnoweb\Hr\Models\Position;
use Karnoweb\Hr\Models\Workflow;
use Karnoweb\Hr\Models\WorkflowStep;
use Karnoweb\Hr\Tests\Fixtures\User;
use Karnoweb\Hr\Tests\TestCase;

class WorkflowTest extends TestCase
{
    public function test_headline_regression_position_and_department_head_steps(): void
    {
        [$employee, $department, $position, $headUser, $managerUser] = $this->orgFixture();

        Hr::employees()->assignPosition($employee, $department->id, $position->id, '2026-01-01');

        $workflow = Workflow::query()->create([
            'branch_id' => 1,
            'name' => 'Leave approval',
            'document_type' => DocumentType::Leave->value,
            'is_active' => true,
            'priority' => 10,
            'execution_mode' => WorkflowExecutionMode::Parallel,
        ]);

        WorkflowStep::query()->create([
            'workflow_id' => $workflow->id,
            'order' => 1,
            'name' => 'Department head',
            'approver_type' => ApproverType::DepartmentHead,
        ]);

        WorkflowStep::query()->create([
            'workflow_id' => $workflow->id,
            'order' => 2,
            'name' => 'Manager position',
            'approver_type' => ApproverType::Position,
            'approver_id' => $position->id,
        ]);

        $document = Hr::documents()->create(DocumentType::Leave, $employee, ['days' => 2]);
        Hr::documents()->submit($document, actorId: $employee->employable_id);

        $approvals = $document->fresh()->approvals()->orderBy('id')->get();
        $this->assertCount(2, $approvals);

        foreach ($approvals as $approval) {
            Hr::documents()->approve($approval, 'ok', actorId: $approval->assigned_to);
        }

        $this->assertSame(DocumentStatus::Approved, $document->fresh()->status);
        $this->assertSame($headUser->id, (int) $approvals[0]->assigned_to);
        $this->assertSame($managerUser->id, (int) $approvals[1]->assigned_to);
    }

    public function test_step_condition_mismatch_is_skipped_at_submit(): void
    {
        $employee = Hr::employees()->createForUser($this->makeUser(), ['branch_id' => 1]);
        $workflow = $this->simpleWorkflow();

        WorkflowStep::query()->create([
            'workflow_id' => $workflow->id,
            'order' => 2,
            'name' => 'Long leave only',
            'approver_type' => ApproverType::User,
            'approver_id' => 9001,
            'condition' => ['field' => 'days', 'operator' => 'gte', 'value' => 5],
        ]);

        $document = Hr::documents()->create(DocumentType::Leave, $employee, ['days' => 2]);
        Hr::documents()->submit($document, actorId: 1);

        $skipped = $document->approvals()->where('status', ApprovalStatus::Skipped)->count();
        $this->assertSame(1, $skipped);
    }

    public function test_optional_pending_step_does_not_block_completion(): void
    {
        $employee = Hr::employees()->createForUser($this->makeUser(), ['branch_id' => 1]);
        $workflow = $this->simpleWorkflow();

        WorkflowStep::query()->create([
            'workflow_id' => $workflow->id,
            'order' => 2,
            'name' => 'Optional FYI',
            'approver_type' => ApproverType::User,
            'approver_id' => 9100,
            'is_required' => false,
        ]);

        $document = Hr::documents()->create(DocumentType::Leave, $employee);
        Hr::documents()->submit($document, actorId: 1);

        $required = $document->approvals()->whereHas('step', fn ($q) => $q->where('is_required', true))->first();
        Hr::documents()->approve($required, null, actorId: $required->assigned_to);

        $this->assertSame(DocumentStatus::Approved, $document->fresh()->status);
    }

    public function test_sequential_workflow_activates_second_step_after_first_approval(): void
    {
        $employee = Hr::employees()->createForUser($this->makeUser(), ['branch_id' => 1]);

        $workflow = Workflow::query()->create([
            'branch_id' => 1,
            'name' => 'Sequential leave',
            'document_type' => DocumentType::Leave->value,
            'is_active' => true,
            'priority' => 20,
            'execution_mode' => WorkflowExecutionMode::Sequential,
        ]);

        WorkflowStep::query()->create([
            'workflow_id' => $workflow->id,
            'order' => 1,
            'name' => 'First',
            'approver_type' => ApproverType::User,
            'approver_id' => 9201,
        ]);

        WorkflowStep::query()->create([
            'workflow_id' => $workflow->id,
            'order' => 2,
            'name' => 'Second',
            'approver_type' => ApproverType::User,
            'approver_id' => 9202,
        ]);

        $document = Hr::documents()->create(DocumentType::Leave, $employee);
        Hr::documents()->submit($document, actorId: 1);

        $this->assertSame(1, $document->approvals()->count());

        $first = $document->approvals()->first();
        Hr::documents()->approve($first, null, actorId: 9201);

        $this->assertSame(2, $document->fresh()->approvals()->count());
    }

    public function test_sequential_optional_only_order_advances_to_required_step(): void
    {
        $employee = Hr::employees()->createForUser($this->makeUser(), ['branch_id' => 1]);

        $workflow = Workflow::query()->create([
            'branch_id' => 1,
            'name' => 'Optional then required',
            'document_type' => DocumentType::Leave->value,
            'is_active' => true,
            'priority' => 30,
            'execution_mode' => WorkflowExecutionMode::Sequential,
        ]);

        WorkflowStep::query()->create([
            'workflow_id' => $workflow->id,
            'order' => 1,
            'name' => 'Optional A',
            'approver_type' => ApproverType::User,
            'approver_id' => 9301,
            'is_required' => false,
        ]);

        WorkflowStep::query()->create([
            'workflow_id' => $workflow->id,
            'order' => 1,
            'name' => 'Optional B',
            'approver_type' => ApproverType::User,
            'approver_id' => 9302,
            'is_required' => false,
        ]);

        WorkflowStep::query()->create([
            'workflow_id' => $workflow->id,
            'order' => 2,
            'name' => 'Required C',
            'approver_type' => ApproverType::User,
            'approver_id' => 9303,
            'is_required' => true,
        ]);

        $document = Hr::documents()->create(DocumentType::Leave, $employee);
        Hr::documents()->submit($document, actorId: 1);

        $this->assertSame(DocumentStatus::Pending, $document->fresh()->status);
        $this->assertSame(2, $document->approvals()->count());

        foreach ($document->approvals()->get() as $approval) {
            Hr::documents()->approve($approval, null, actorId: $approval->assigned_to);
        }

        $document = $document->fresh();
        $this->assertSame(3, $document->approvals()->count());
        $this->assertSame(DocumentStatus::Pending, $document->status);

        $required = $document->approvals()->whereHas('step', fn ($q) => $q->where('is_required', true))->first();
        Hr::documents()->approve($required, null, actorId: 9303);

        $this->assertSame(DocumentStatus::Approved, $document->fresh()->status);
    }

    public function test_reject_is_blocked_when_step_disallows_it(): void
    {
        $employee = Hr::employees()->createForUser($this->makeUser(), ['branch_id' => 1]);
        $workflow = $this->simpleWorkflow();

        $step = $workflow->steps()->first();
        $step->update(['can_reject' => false]);

        $document = Hr::documents()->create(DocumentType::Leave, $employee);
        Hr::documents()->submit($document, actorId: 1);

        $approval = $document->approvals()->first();

        $this->expectException(\InvalidArgumentException::class);
        Hr::documents()->reject($approval, 'no', actorId: $approval->assigned_to);
    }

    public function test_timeout_auto_approve_completes_document(): void
    {
        Carbon::setTestNow('2026-03-01 10:00:00');

        $employee = Hr::employees()->createForUser($this->makeUser(), ['branch_id' => 1]);
        $workflow = $this->simpleWorkflow();
        $workflow->steps()->first()->update([
            'timeout_hours' => 1,
            'timeout_action' => TimeoutAction::AutoApprove,
        ]);

        $document = Hr::documents()->create(DocumentType::Leave, $employee);
        Hr::documents()->submit($document, actorId: 1);

        Carbon::setTestNow('2026-03-01 12:00:00');
        Artisan::call('hr:process-workflow-timeouts');

        $this->assertSame(DocumentStatus::Approved, $document->fresh()->status);
    }

    public function test_timeout_escalate_reassigns_approval(): void
    {
        Carbon::setTestNow('2026-03-01 10:00:00');

        config(['hr.workflow.escalation_fallback_user_id' => 9900]);

        $employee = Hr::employees()->createForUser($this->makeUser(), ['branch_id' => 1]);
        $workflow = $this->simpleWorkflow();
        $workflow->steps()->first()->update([
            'timeout_hours' => 1,
            'timeout_action' => TimeoutAction::Escalate,
        ]);

        $document = Hr::documents()->create(DocumentType::Leave, $employee);
        Hr::documents()->submit($document, actorId: 1);

        Carbon::setTestNow('2026-03-01 12:00:00');
        Artisan::call('hr:process-workflow-timeouts');

        $approval = $document->fresh()->approvals()->first();
        $this->assertSame(9900, (int) $approval->assigned_to);
        $this->assertSame(ApprovalStatus::Pending, $approval->status);
    }

    public function test_cancel_marks_pending_approvals_skipped(): void
    {
        $employee = Hr::employees()->createForUser($this->makeUser(), ['branch_id' => 1]);
        $this->simpleWorkflow();

        $document = Hr::documents()->create(DocumentType::Leave, $employee);
        Hr::documents()->submit($document, actorId: 1);

        Hr::documents()->cancel($document, actorId: 1, reason: 'withdrawn');

        $this->assertSame(DocumentStatus::Cancelled, $document->fresh()->status);
        $this->assertSame(
            0,
            $document->approvals()->where('status', ApprovalStatus::Pending)->count()
        );
    }

    public function test_submit_throws_when_department_head_cannot_be_resolved(): void
    {
        $employee = Hr::employees()->createForUser($this->makeUser(), ['branch_id' => 1]);
        $workflow = Workflow::query()->create([
            'branch_id' => 1,
            'name' => 'Broken',
            'document_type' => DocumentType::Leave->value,
            'is_active' => true,
            'priority' => 5,
        ]);

        WorkflowStep::query()->create([
            'workflow_id' => $workflow->id,
            'order' => 1,
            'name' => 'Head',
            'approver_type' => ApproverType::DepartmentHead,
        ]);

        $document = Hr::documents()->create(DocumentType::Leave, $employee);

        $this->expectException(UnresolvableApproverException::class);
        Hr::documents()->submit($document);
    }

    public function test_custom_approver_resolver_is_used(): void
    {
        config(['hr.workflow.custom_approver_resolver' => fn () => 8801]);

        $employee = Hr::employees()->createForUser($this->makeUser(), ['branch_id' => 1]);
        $workflow = Workflow::query()->create([
            'branch_id' => 1,
            'name' => 'Custom',
            'document_type' => DocumentType::Leave->value,
            'is_active' => true,
            'priority' => 5,
        ]);

        WorkflowStep::query()->create([
            'workflow_id' => $workflow->id,
            'order' => 1,
            'name' => 'Custom step',
            'approver_type' => ApproverType::Custom,
        ]);

        $document = Hr::documents()->create(DocumentType::Leave, $employee);
        Hr::documents()->submit($document, actorId: 1);

        $this->assertSame(8801, (int) $document->approvals()->first()->assigned_to);
    }

    protected function simpleWorkflow(): Workflow
    {
        $workflow = Workflow::query()->create([
            'branch_id' => 1,
            'name' => 'Simple',
            'document_type' => DocumentType::Leave->value,
            'is_active' => true,
            'priority' => 1,
            'execution_mode' => WorkflowExecutionMode::Parallel,
        ]);

        WorkflowStep::query()->create([
            'workflow_id' => $workflow->id,
            'order' => 1,
            'name' => 'Manager',
            'approver_type' => ApproverType::User,
            'approver_id' => 9300,
        ]);

        return $workflow;
    }

    /**
     * @return array{0: Employee, 1: Department, 2: Position, 3: User, 4: User}
     */
    protected function orgFixture(): array
    {
        $headUser = $this->makeUser();
        $managerUser = $this->makeUser();
        $employeeUser = $this->makeUser();

        $headEmployee = Hr::employees()->createForUser($headUser, ['branch_id' => 1]);
        $managerEmployee = Hr::employees()->createForUser($managerUser, ['branch_id' => 1]);
        $employee = Hr::employees()->createForUser($employeeUser, ['branch_id' => 1]);

        $department = Department::query()->create([
            'branch_id' => 1,
            'code' => 'HR-DEPT',
            'name' => 'HR',
            'head_employee_id' => $headEmployee->id,
        ]);

        $position = Position::query()->create([
            'branch_id' => 1,
            'code' => 'MGR',
            'name' => 'Manager',
        ]);

        Hr::employees()->assignPosition($managerEmployee, $department->id, $position->id, '2026-01-01');

        return [$employee, $department, $position, $headUser, $managerUser];
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
