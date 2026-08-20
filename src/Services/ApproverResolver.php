<?php

namespace Karnoweb\Hr\Services;

use InvalidArgumentException;
use Karnoweb\Hr\Enums\ApproverType;
use Karnoweb\Hr\Exceptions\UnresolvableApproverException;
use Karnoweb\Hr\Models\Department;
use Karnoweb\Hr\Models\Employee;
use Karnoweb\Hr\Models\EmployeePosition;
use Karnoweb\Hr\Models\HrDocument;
use Karnoweb\Hr\Models\WorkflowStep;

/**
 * Resolve workflow steps to concrete user ids for DocumentApproval.assigned_to (HR-125).
 */
class ApproverResolver
{
    /**
     * @throws UnresolvableApproverException
     */
    public function resolve(HrDocument $document, WorkflowStep $step): int
    {
        $type = $step->approver_type;

        return match ($type) {
            ApproverType::User => $this->resolveUser($step),
            ApproverType::DepartmentHead => $this->resolveDepartmentHead($document),
            ApproverType::Position => $this->resolvePositionHolder($step),
            ApproverType::Custom => $this->resolveCustom($document, $step),
        };
    }

    /**
     * Validate that a step can be resolved in principle (HR-136).
     *
     * @throws InvalidArgumentException
     */
    public function validateStepConfiguration(WorkflowStep $step): void
    {
        $type = $step->approver_type;

        match ($type) {
            ApproverType::User => $this->assertUserStep($step),
            ApproverType::DepartmentHead => null,
            ApproverType::Position => $this->assertPositionStep($step),
            ApproverType::Custom => $this->assertCustomConfigured(),
        };
    }

    protected function resolveUser(WorkflowStep $step): int
    {
        if ($step->approver_id === null) {
            throw new UnresolvableApproverException('User approver step is missing approver_id.');
        }

        return (int) $step->approver_id;
    }

    protected function resolveDepartmentHead(HrDocument $document): int
    {
        $employee = $this->loadEmployee($document);
        /** @var EmployeePosition|null $assignment */
        $assignment = $employee->currentPosition()->first();

        if ($assignment === null || $assignment->department_id === null) {
            throw new UnresolvableApproverException(
                'Cannot resolve department head: employee has no current primary department assignment.'
            );
        }

        $department = Department::query()->find($assignment->department_id);

        if ($department === null || $department->head_employee_id === null) {
            throw new UnresolvableApproverException(
                'Cannot resolve department head: department has no head_employee_id configured.'
            );
        }

        $head = Employee::query()->find($department->head_employee_id);

        if ($head === null) {
            throw new UnresolvableApproverException('Cannot resolve department head: head employee record is missing.');
        }

        return $this->userIdForEmployee($head);
    }

    protected function resolvePositionHolder(WorkflowStep $step): int
    {
        if ($step->approver_id === null) {
            throw new UnresolvableApproverException('Position approver step is missing approver_id (position id).');
        }

        $assignment = EmployeePosition::query()
            ->where('position_id', $step->approver_id)
            ->whereNull('end_date')
            ->orderByDesc('effective_date')
            ->first();

        if ($assignment === null) {
            throw new UnresolvableApproverException(
                "No active employee holds position #{$step->approver_id}."
            );
        }

        $holder = Employee::query()->find($assignment->employee_id);

        if ($holder === null) {
            throw new UnresolvableApproverException('Position holder employee record is missing.');
        }

        return $this->userIdForEmployee($holder);
    }

    protected function resolveCustom(HrDocument $document, WorkflowStep $step): int
    {
        $resolver = config('hr.workflow.custom_approver_resolver');

        if (! is_callable($resolver)) {
            throw new UnresolvableApproverException(
                'Custom approver resolver is not configured (hr.workflow.custom_approver_resolver).'
            );
        }

        $userId = $resolver($document, $step);

        if ($userId === null) {
            throw new UnresolvableApproverException('Custom approver resolver returned no user id.');
        }

        return (int) $userId;
    }

    /**
     * Escalation target for timeout processing (HR-132).
     *
     * @throws UnresolvableApproverException
     */
    public function resolveEscalationTarget(HrDocument $document, WorkflowStep $step, int $currentAssigneeUserId): int
    {
        if ($step->escalation_user_id !== null) {
            return (int) $step->escalation_user_id;
        }

        $fallback = config('hr.workflow.escalation_fallback_user_id');

        if ($fallback !== null) {
            return (int) $fallback;
        }

        throw new UnresolvableApproverException(
            'No escalation target configured for workflow step and no hr.workflow.escalation_fallback_user_id fallback.'
        );
    }

    protected function loadEmployee(HrDocument $document): Employee
    {
        $employee = $document->employee;

        if ($employee === null) {
            throw new UnresolvableApproverException('Document has no associated employee.');
        }

        return $employee;
    }

    protected function userIdForEmployee(Employee $employee): int
    {
        $userClass = config('hr.models.user');

        if ($employee->employable_type !== $userClass) {
            throw new UnresolvableApproverException(
                'Employee employable is not the configured HR user model; cannot map to approval user id.'
            );
        }

        return (int) $employee->employable_id;
    }

    protected function assertUserStep(WorkflowStep $step): void
    {
        if ($step->approver_id === null) {
            throw new InvalidArgumentException('User approver steps require approver_id (user id).');
        }
    }

    protected function assertPositionStep(WorkflowStep $step): void
    {
        if ($step->approver_id === null) {
            throw new InvalidArgumentException('Position approver steps require approver_id (position id).');
        }
    }

    protected function assertCustomConfigured(): void
    {
        if (! is_callable(config('hr.workflow.custom_approver_resolver'))) {
            throw new InvalidArgumentException(
                'Custom approver steps require hr.workflow.custom_approver_resolver to be callable.'
            );
        }
    }
}
