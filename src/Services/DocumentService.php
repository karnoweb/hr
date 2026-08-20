<?php

namespace Karnoweb\Hr\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Karnoweb\Hr\Enums\ApprovalStatus;
use Karnoweb\Hr\Enums\DocumentStatus;
use Karnoweb\Hr\Enums\DocumentType;
use Karnoweb\Hr\Enums\TimeoutAction;
use Karnoweb\Hr\Exceptions\DocumentLockedException;
use Karnoweb\Hr\Exceptions\UnauthorizedApprovalException;
use Karnoweb\Hr\Exceptions\UnresolvableApproverException;
use Karnoweb\Hr\Exceptions\UnresolvableWorkflowException;
use Karnoweb\Hr\Models\DocumentApproval;
use Karnoweb\Hr\Models\DocumentHistory;
use Karnoweb\Hr\Models\Employee;
use Karnoweb\Hr\Models\HrDocument;
use Karnoweb\Hr\Models\Workflow;
use Karnoweb\Hr\Models\WorkflowStep;

/**
 * Service for HR documents: create, submit, approve, reject with workflow and history.
 *
 * `approve()` / `reject()` verify the actor matches `DocumentApproval.assigned_to` —
 * the package's first in-package authorization check (HR-120).
 *
 * @see HrDocument
 * @see DocumentApproval
 * @see Workflow
 */
class DocumentService
{
    public function __construct(
        protected WorkflowEngine $workflowEngine,
    ) {}

    /**
     * Create a new HR document (draft) for the given type and employee.
     *
     * @param  array<string, mixed>  $data
     * @param  array{branch_id?: int|null, effective_date?: \DateTimeInterface|string, expiry_date?: \DateTimeInterface|string|null, notes?: string|null, created_by?: int|null, metadata?: array|null, allow_branch_override?: bool}  $options
     */
    public function create(DocumentType $type, Employee $employee, array $data = [], array $options = []): HrDocument
    {
        $branchId = $options['branch_id'] ?? $employee->branch_id;

        if (
            $branchId !== $employee->branch_id
            && ! ($options['allow_branch_override'] ?? false)
        ) {
            throw new InvalidArgumentException(
                'branch_id must match the employee branch unless allow_branch_override is true.'
            );
        }

        $effectiveDate = $options['effective_date'] ?? now();
        if (! $effectiveDate instanceof \DateTimeInterface) {
            $effectiveDate = Carbon::parse($effectiveDate);
        }

        return HrDocument::create([
            'branch_id' => $branchId,
            'employee_id' => $employee->id,
            'type' => $type,
            'effective_date' => $effectiveDate,
            'expiry_date' => $options['expiry_date'] ?? null,
            'status' => DocumentStatus::Draft,
            'data' => $data,
            'notes' => $options['notes'] ?? null,
            'created_by' => $options['created_by'] ?? null,
            'metadata' => $options['metadata'] ?? null,
        ]);
    }

    public function resubmit(HrDocument $document, int|string|null $actorId = null): HrDocument
    {
        if ($document->status !== DocumentStatus::Rejected) {
            throw new InvalidArgumentException('Only rejected documents can be resubmitted.');
        }

        $employee = $document->employee;

        if (! $employee instanceof Employee) {
            throw new InvalidArgumentException('Document has no associated employee.');
        }

        return $this->create(
            $document->type,
            $employee,
            $document->data ?? [],
            [
                'branch_id' => $document->branch_id,
                'effective_date' => $document->effective_date,
                'expiry_date' => $document->expiry_date,
                'notes' => $document->notes,
                'created_by' => $this->resolveActorId($actorId),
                'metadata' => array_merge($document->metadata ?? [], [
                    'resubmitted_from' => $document->id,
                ]),
                'allow_branch_override' => true,
            ]
        );
    }

    /**
     * @throws DocumentLockedException
     * @throws UnresolvableWorkflowException
     * @throws UnresolvableApproverException
     */
    public function submit(HrDocument $document, int|string|null $actorId = null): HrDocument
    {
        $document->ensureEditable();
        $actorId = $this->resolveActorId($actorId);

        $workflow = Workflow::findForDocument($document);

        if ($workflow === null) {
            if (config('hr.workflow.skip_on_no_approver', false)) {
                return DB::transaction(function () use ($document, $actorId) {
                    $this->finalizeApproved($document, $actorId, DocumentStatus::Draft);

                    return $document->fresh();
                });
            }

            throw new UnresolvableWorkflowException(
                "No active workflow found for document type [{$document->type->value}]."
            );
        }

        $plan = $this->workflowEngine->planInitialApprovals($workflow, $document, $actorId);

        if ($plan === []) {
            throw new UnresolvableWorkflowException(
                'Workflow conditions did not match this document.'
            );
        }

        return DB::transaction(function () use ($document, $workflow, $actorId, $plan) {
            $document->update(['status' => DocumentStatus::Pending]);

            foreach ($plan as $payload) {
                DocumentApproval::create([
                    'hr_document_id' => $document->id,
                    'workflow_step_id' => $payload['step']->id,
                    'assigned_to' => $payload['assigned_to'],
                    'status' => $payload['status'],
                    'comment' => $payload['comment'],
                    'acted_at' => $payload['status'] !== ApprovalStatus::Pending ? now() : null,
                    'deadline_at' => $payload['deadline_at'],
                ]);
            }

            $this->recordHistory(
                $document,
                'submit',
                DocumentStatus::Draft,
                DocumentStatus::Pending,
                $document->getAttributes(),
                $actorId
            );

            $document = $document->fresh();
            $this->workflowEngine->activateNextSequentialSteps($document, $workflow);
            $this->advanceDocumentStatusIfComplete($document, $actorId, $workflow);

            return $document->fresh();
        });
    }

    public function approve(DocumentApproval $approval, ?string $comment = null, int|string|null $actorId = null): DocumentApproval
    {
        $actorId = $this->resolveActorId($actorId);

        return DB::transaction(function () use ($approval, $comment, $actorId) {
            $document = HrDocument::query()
                ->whereKey($approval->hr_document_id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureDocumentPending($document);

            $approval = DocumentApproval::query()
                ->whereKey($approval->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($approval->status !== ApprovalStatus::Pending) {
                throw new InvalidArgumentException('Approval is not pending.');
            }

            $this->ensureActorAuthorized($approval, $actorId);

            $approval->update([
                'status' => ApprovalStatus::Approved,
                'comment' => $comment,
                'acted_at' => now(),
            ]);

            $this->recordHistory($document, 'approve_step', DocumentStatus::Pending, DocumentStatus::Pending, [
                'workflow_step_id' => $approval->workflow_step_id,
                'approval_id' => $approval->id,
            ], $actorId);

            $workflow = Workflow::findForDocument($document);

            if ($workflow !== null) {
                $this->workflowEngine->activateNextSequentialSteps($document->fresh(), $workflow);
            }

            $this->advanceDocumentStatusIfComplete($document->fresh(), $actorId, $workflow);

            return $approval->fresh();
        });
    }

    public function reject(DocumentApproval $approval, ?string $comment = null, int|string|null $actorId = null): DocumentApproval
    {
        $actorId = $this->resolveActorId($actorId);

        return DB::transaction(function () use ($approval, $comment, $actorId) {
            $document = HrDocument::query()
                ->whereKey($approval->hr_document_id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureDocumentPending($document);

            $approval = DocumentApproval::query()
                ->whereKey($approval->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($approval->status !== ApprovalStatus::Pending) {
                throw new InvalidArgumentException('Approval is not pending.');
            }

            $this->ensureActorAuthorized($approval, $actorId);
            $this->ensureStepCanReject($approval);

            $approval->update([
                'status' => ApprovalStatus::Rejected,
                'comment' => $comment,
                'acted_at' => now(),
            ]);

            $document->update(['status' => DocumentStatus::Rejected]);

            DocumentApproval::query()
                ->where('hr_document_id', $document->id)
                ->where('status', ApprovalStatus::Pending)
                ->whereKeyNot($approval->id)
                ->update([
                    'status' => ApprovalStatus::Skipped,
                    'acted_at' => now(),
                ]);

            $this->recordHistory($document, 'reject', DocumentStatus::Pending, DocumentStatus::Rejected, [
                'workflow_step_id' => $approval->workflow_step_id,
                'comment' => $comment,
            ], $actorId);

            return $approval->fresh();
        });
    }

    /**
     * Cancel a pending document before any approval acts (HR-133).
     */
    public function cancel(HrDocument $document, int|string|null $actorId = null, ?string $reason = null): HrDocument
    {
        if ($document->status !== DocumentStatus::Pending) {
            throw new InvalidArgumentException('Only pending documents can be cancelled.');
        }

        $actorId = $this->resolveActorId($actorId);

        return DB::transaction(function () use ($document, $actorId, $reason) {
            $document = HrDocument::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();

            DocumentApproval::query()
                ->where('hr_document_id', $document->id)
                ->where('status', ApprovalStatus::Pending)
                ->update([
                    'status' => ApprovalStatus::Skipped,
                    'acted_at' => now(),
                    'comment' => 'Cancelled',
                ]);

            $document->update(['status' => DocumentStatus::Cancelled]);

            $this->recordHistory(
                $document,
                'cancel',
                DocumentStatus::Pending,
                DocumentStatus::Cancelled,
                ['reason' => $reason],
                $actorId
            );

            return $document->fresh();
        });
    }

    /**
     * Apply a timeout action to an overdue pending approval (HR-131).
     */
    public function applyTimeoutAction(DocumentApproval $approval): DocumentApproval
    {
        return DB::transaction(function () use ($approval) {
            $approval = DocumentApproval::query()->whereKey($approval->id)->lockForUpdate()->firstOrFail();

            if ($approval->status !== ApprovalStatus::Pending) {
                return $approval;
            }

            $document = HrDocument::query()
                ->whereKey($approval->hr_document_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($document->status !== DocumentStatus::Pending) {
                return $approval;
            }

            $step = $approval->step;

            if (! $step instanceof WorkflowStep) {
                throw new InvalidArgumentException('Approval has no workflow step.');
            }

            $action = $this->timeoutAction($step);

            return match ($action) {
                TimeoutAction::AutoApprove => $this->systemApprove($approval, $document, 'Timeout auto-approve'),
                TimeoutAction::AutoReject => $this->systemReject($approval, $document, 'Timeout auto-reject'),
                TimeoutAction::Skip => $this->systemSkip($approval, $document),
                TimeoutAction::Escalate => $this->systemEscalate($approval, $document, $step),
            };
        });
    }

    private function advanceDocumentStatusIfComplete(
        HrDocument $document,
        int|string|null $actorId,
        ?Workflow $workflow = null,
    ): void {
        if ($workflow !== null && $this->workflowEngine->hasIncompleteSequentialOrders($document, $workflow)) {
            return;
        }

        if ($this->workflowEngine->hasBlockingRequiredPending($document)) {
            return;
        }

        if ($this->workflowEngine->hasRejectedRequired($document)) {
            $document->update(['status' => DocumentStatus::Rejected]);

            return;
        }

        $this->finalizeApproved($document, $actorId);
    }

    private function finalizeApproved(
        HrDocument $document,
        int|string|null $actorId,
        DocumentStatus $fromStatus = DocumentStatus::Pending
    ): void {
        $document->update([
            'status' => DocumentStatus::Approved,
            'approved_by' => $actorId,
            'approved_at' => now(),
        ]);

        if (config('hr.documents.auto_lock_after_approval', true)) {
            $delayHours = config('hr.documents.lock_delay_hours', 24);
            $document->update([
                'locked_at' => now()->addHours($delayHours),
            ]);
        }

        $this->recordHistory($document, 'approved', $fromStatus, DocumentStatus::Approved, [], $actorId);
    }

    private function systemApprove(DocumentApproval $approval, HrDocument $document, string $comment): DocumentApproval
    {
        $approval->update([
            'status' => ApprovalStatus::Approved,
            'comment' => $comment,
            'acted_at' => now(),
        ]);

        $workflow = Workflow::findForDocument($document);

        if ($workflow !== null) {
            $this->workflowEngine->activateNextSequentialSteps($document->fresh(), $workflow);
        }

        $this->advanceDocumentStatusIfComplete($document->fresh(), null, $workflow);

        return $approval->fresh();
    }

    private function systemReject(DocumentApproval $approval, HrDocument $document, string $comment): DocumentApproval
    {
        $approval->update([
            'status' => ApprovalStatus::Rejected,
            'comment' => $comment,
            'acted_at' => now(),
        ]);

        $document->update(['status' => DocumentStatus::Rejected]);

        DocumentApproval::query()
            ->where('hr_document_id', $document->id)
            ->where('status', ApprovalStatus::Pending)
            ->whereKeyNot($approval->id)
            ->update([
                'status' => ApprovalStatus::Skipped,
                'acted_at' => now(),
            ]);

        return $approval->fresh();
    }

    private function systemSkip(DocumentApproval $approval, HrDocument $document): DocumentApproval
    {
        $approval->update([
            'status' => ApprovalStatus::Skipped,
            'comment' => 'Timeout skip',
            'acted_at' => now(),
        ]);

        $workflow = Workflow::findForDocument($document);

        if ($workflow !== null) {
            $this->workflowEngine->activateNextSequentialSteps($document->fresh(), $workflow);
        }

        $this->advanceDocumentStatusIfComplete($document->fresh(), null, $workflow);

        return $approval->fresh();
    }

    private function systemEscalate(DocumentApproval $approval, HrDocument $document, WorkflowStep $step): DocumentApproval
    {
        $resolver = app(ApproverResolver::class);
        $newAssignee = $resolver->resolveEscalationTarget($document, $step, (int) $approval->assigned_to);

        $approval->update([
            'assigned_to' => $newAssignee,
            'deadline_at' => $step->timeout_hours ? now()->addHours((int) $step->timeout_hours) : null,
            'comment' => 'Escalated after timeout',
        ]);

        return $approval->fresh();
    }

    private function timeoutAction(WorkflowStep $step): TimeoutAction
    {
        $raw = $step->timeout_action;

        if ($raw === null) {
            return TimeoutAction::AutoApprove;
        }

        $action = $raw instanceof TimeoutAction ? $raw : TimeoutAction::tryFrom((string) $raw);

        return $action ?? TimeoutAction::AutoApprove;
    }

    private function ensureDocumentPending(HrDocument $document): void
    {
        if ($document->status !== DocumentStatus::Pending) {
            throw new InvalidArgumentException(
                "Document is not pending (current: {$document->status->value})."
            );
        }
    }

    private function ensureStepCanReject(DocumentApproval $approval): void
    {
        $step = $approval->step;

        if ($step instanceof WorkflowStep && ! $step->can_reject) {
            throw new InvalidArgumentException('This workflow step does not allow rejection.');
        }
    }

    private function ensureActorAuthorized(DocumentApproval $approval, int|string|null $actorId): void
    {
        if ($actorId === null) {
            throw new UnauthorizedApprovalException(
                'An actor id is required to approve or reject this step.'
            );
        }

        if ((int) $approval->assigned_to !== (int) $actorId) {
            throw new UnauthorizedApprovalException(
                'The acting user is not assigned to this approval step.'
            );
        }
    }

    private function resolveActorId(int|string|null $actorId): int|string|null
    {
        return $actorId ?? auth()->id();
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function recordHistory(
        HrDocument $document,
        string $action,
        ?DocumentStatus $fromStatus,
        ?DocumentStatus $toStatus,
        array $changes,
        int|string|null $actorId = null,
    ): void {
        DocumentHistory::create([
            'hr_document_id' => $document->id,
            'user_id' => $actorId ?? auth()->id(),
            'action' => $action,
            'from_status' => $fromStatus?->value,
            'to_status' => $toStatus?->value,
            'changes' => $changes,
        ]);
    }
}
