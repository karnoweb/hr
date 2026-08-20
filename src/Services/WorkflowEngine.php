<?php

namespace Karnoweb\Hr\Services;

use Carbon\Carbon;
use Karnoweb\Hr\Enums\ApprovalStatus;
use Karnoweb\Hr\Enums\WorkflowExecutionMode;
use Karnoweb\Hr\Models\DocumentApproval;
use Karnoweb\Hr\Models\HrDocument;
use Karnoweb\Hr\Models\Workflow;
use Karnoweb\Hr\Models\WorkflowStep;

/**
 * Plans and advances workflow approvals (HR-126–HR-130 / HR-135).
 */
class WorkflowEngine
{
    public function __construct(
        protected ApproverResolver $approvers,
        protected ConditionEvaluator $conditions,
    ) {}

    /**
     * Pure read pass: resolve approvers and initial statuses before the submit transaction.
     *
     * @return list<array{
     *     step: WorkflowStep,
     *     assigned_to: int,
     *     status: ApprovalStatus,
     *     deadline_at: Carbon|null,
     *     comment: string|null
     * }>
     */
    public function planInitialApprovals(
        Workflow $workflow,
        HrDocument $document,
        int|string|null $submitterUserId,
    ): array {
        if (! $this->conditions->matches($workflow->conditions, $document)) {
            return [];
        }

        $workflow->loadMissing('steps');

        $steps = $this->stepsForInitialActivation($workflow);

        $planned = [];

        foreach ($steps as $step) {
            $planned = array_merge($planned, $this->planStep($step, $document, $submitterUserId));
        }

        return $planned;
    }

    /**
     * Create approval rows for the next sequential order group after a step completes.
     *
     * @return list<DocumentApproval>
     */
    public function activateNextSequentialSteps(HrDocument $document, Workflow $workflow): array
    {
        if ($this->executionMode($workflow) !== WorkflowExecutionMode::Sequential) {
            return [];
        }

        $workflow->loadMissing('steps');
        $existingStepIds = $document->approvals()->pluck('workflow_step_id')->all();
        $completedOrders = $this->completedOrders($document, $workflow);

        if ($completedOrders === []) {
            return [];
        }

        $nextOrder = max($completedOrders) + 1;
        $nextSteps = $workflow->steps->where('order', $nextOrder);

        $created = [];

        foreach ($nextSteps as $step) {
            if (in_array($step->id, $existingStepIds, true)) {
                continue;
            }

            foreach ($this->planStep($step, $document, $document->created_by) as $payload) {
                $created[] = DocumentApproval::create([
                    'hr_document_id' => $document->id,
                    'workflow_step_id' => $payload['step']->id,
                    'assigned_to' => $payload['assigned_to'],
                    'status' => $payload['status'],
                    'comment' => $payload['comment'],
                    'acted_at' => $payload['status'] !== ApprovalStatus::Pending ? now() : null,
                    'deadline_at' => $payload['deadline_at'],
                ]);
            }
        }

        return $created;
    }

    public function hasBlockingRequiredPending(HrDocument $document): bool
    {
        return $document->approvals()
            ->where('status', ApprovalStatus::Pending)
            ->whereHas('step', fn ($query) => $query->where('is_required', true))
            ->exists();
    }

    public function hasRejectedRequired(HrDocument $document): bool
    {
        return $document->approvals()
            ->where('status', ApprovalStatus::Rejected)
            ->whereHas('step', fn ($query) => $query->where('is_required', true))
            ->exists();
    }

    /**
     * @return iterable<WorkflowStep>
     */
    protected function stepsForInitialActivation(Workflow $workflow): iterable
    {
        $eligible = $workflow->steps->sortBy('order');

        if ($this->executionMode($workflow) === WorkflowExecutionMode::Sequential) {
            $firstOrder = $eligible->min('order');

            return $eligible->where('order', $firstOrder);
        }

        return $eligible;
    }

    /**
     * @return list<array{
     *     step: WorkflowStep,
     *     assigned_to: int,
     *     status: ApprovalStatus,
     *     deadline_at: Carbon|null,
     *     comment: string|null
     * }>
     */
    protected function planStep(
        WorkflowStep $step,
        HrDocument $document,
        int|string|null $submitterUserId,
    ): array {
        if (! $this->conditions->matches($step->condition, $document)) {
            return [[
                'step' => $step,
                'assigned_to' => $this->fallbackAssignee($document, $submitterUserId),
                'status' => ApprovalStatus::Skipped,
                'deadline_at' => null,
                'comment' => 'Condition not matched',
            ]];
        }

        $assignedTo = $this->approvers->resolve($document, $step);

        if ($this->shouldAutoApproveOwnDepartment($document, $submitterUserId, $assignedTo)) {
            return [[
                'step' => $step,
                'assigned_to' => $assignedTo,
                'status' => ApprovalStatus::Approved,
                'deadline_at' => null,
                'comment' => 'Auto-approved (own department policy)',
            ]];
        }

        return [[
            'step' => $step,
            'assigned_to' => $assignedTo,
            'status' => ApprovalStatus::Pending,
            'deadline_at' => $step->timeout_hours ? now()->addHours((int) $step->timeout_hours) : null,
            'comment' => null,
        ]];
    }

    protected function shouldAutoApproveOwnDepartment(
        HrDocument $document,
        int|string|null $submitterUserId,
        int $assignedTo,
    ): bool {
        if (! config('hr.workflow.auto_approve_own_department', false)) {
            return false;
        }

        $submitterUserId = $submitterUserId ?? $document->created_by;

        if ($submitterUserId !== null && (int) $submitterUserId === $assignedTo) {
            return true;
        }

        $employee = $document->employee;
        $userClass = config('hr.models.user');

        if (
            $employee !== null
            && $employee->employable_type === $userClass
            && (int) $employee->employable_id === $assignedTo
        ) {
            return true;
        }

        return false;
    }

    /**
     * @return list<int>
     */
    protected function completedOrders(HrDocument $document, Workflow $workflow): array
    {
        $orders = [];

        foreach ($workflow->steps->groupBy('order') as $order => $steps) {
            $requiredSteps = $steps->where('is_required', true);

            if ($requiredSteps->isEmpty()) {
                continue;
            }

            $allResolved = true;

            foreach ($requiredSteps as $step) {
                /** @var DocumentApproval|null $approval */
                $approval = $document->approvals()->where('workflow_step_id', $step->id)->first();

                if ($approval === null || $approval->status === ApprovalStatus::Pending) {
                    $allResolved = false;
                    break;
                }
            }

            if ($allResolved) {
                $orders[] = (int) $order;
            }
        }

        return $orders;
    }

    protected function executionMode(Workflow $workflow): WorkflowExecutionMode
    {
        $mode = $workflow->execution_mode ?? WorkflowExecutionMode::Parallel->value;

        return $mode instanceof WorkflowExecutionMode
            ? $mode
            : (WorkflowExecutionMode::tryFrom((string) $mode) ?? WorkflowExecutionMode::Parallel);
    }

    protected function fallbackAssignee(HrDocument $document, int|string|null $submitterUserId): int
    {
        if ($submitterUserId !== null) {
            return (int) $submitterUserId;
        }

        if ($document->created_by !== null) {
            return (int) $document->created_by;
        }

        $employee = $document->employee;
        $userClass = config('hr.models.user');

        if ($employee !== null && $employee->employable_type === $userClass) {
            return (int) $employee->employable_id;
        }

        return 1;
    }
}
