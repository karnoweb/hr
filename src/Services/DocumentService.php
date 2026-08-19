<?php

namespace Karnoweb\Hr\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Karnoweb\Hr\Enums\ApprovalStatus;
use Karnoweb\Hr\Enums\DocumentStatus;
use Karnoweb\Hr\Enums\DocumentType;
use Karnoweb\Hr\Exceptions\DocumentLockedException;
use Karnoweb\Hr\Exceptions\UnauthorizedApprovalException;
use Karnoweb\Hr\Exceptions\UnresolvableWorkflowException;
use Karnoweb\Hr\Models\DocumentApproval;
use Karnoweb\Hr\Models\DocumentHistory;
use Karnoweb\Hr\Models\Employee;
use Karnoweb\Hr\Models\HrDocument;
use Karnoweb\Hr\Models\Workflow;

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
    /**
     * Create a new HR document (draft) for the given type and employee.
     *
     * @param  DocumentType  $type  Document type (e.g. employment, transfer).
     * @param  Employee  $employee  Employee the document belongs to.
     * @param  array<string, mixed>  $data  Document payload (stored in data column).
     * @param  array{branch_id?: int|null, effective_date?: \DateTimeInterface|string, expiry_date?: \DateTimeInterface|string|null, notes?: string|null, created_by?: int|null, metadata?: array|null, allow_branch_override?: bool}  $options  branch_id (defaults to employee branch), effective_date (default now), expiry_date, notes, created_by, metadata, allow_branch_override.
     * @return HrDocument Created document in Draft status.
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

    /**
     * Clone a rejected document into a new Draft for resubmission (HR-122).
     *
     * Rejected documents are terminal for the original row; edits go through a new Draft
     * linked via metadata.resubmitted_from.
     */
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
     * Submit a draft document: set status to Pending and create workflow approvals.
     *
     * @param  HrDocument  $document  Document to submit (must be editable / draft).
     * @return HrDocument Updated document (fresh from DB).
     *
     * @throws DocumentLockedException When document is locked (e.g. already approved and locked).
     * @throws UnresolvableWorkflowException When no workflow exists and skip_on_no_approver is false.
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

        return DB::transaction(function () use ($document, $workflow, $actorId) {
            $document->update(['status' => DocumentStatus::Pending]);

            foreach ($workflow->steps as $step) {
                DocumentApproval::create([
                    'hr_document_id' => $document->id,
                    'workflow_step_id' => $step->id,
                    'assigned_to' => $step->approver_id,
                    'status' => ApprovalStatus::Pending,
                    'deadline_at' => $step->timeout_hours ? now()->addHours($step->timeout_hours) : null,
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

            return $document->fresh();
        });
    }

    /**
     * Approve a pending document approval step; may mark document as Approved when workflow is complete.
     *
     * @param  DocumentApproval  $approval  Pending approval to approve.
     * @param  string|null  $comment  Optional comment.
     * @return DocumentApproval Updated approval (fresh from DB).
     *
     * @throws InvalidArgumentException When approval or document is not pending.
     * @throws UnauthorizedApprovalException When actor does not match assigned_to.
     */
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

            $this->advanceDocumentStatusIfComplete($document, $actorId);

            return $approval->fresh();
        });
    }

    /**
     * Reject a pending document approval; sets document status to Rejected.
     *
     * @param  DocumentApproval  $approval  Pending approval to reject.
     * @param  string|null  $comment  Optional comment.
     * @return DocumentApproval Updated approval (fresh from DB).
     *
     * @throws InvalidArgumentException When approval or document is not pending.
     * @throws UnauthorizedApprovalException When actor does not match assigned_to.
     */
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
     * If all approvals are done (no pending), set document to Approved or Rejected and optionally lock.
     */
    private function advanceDocumentStatusIfComplete(HrDocument $document, int|string|null $actorId): void
    {
        $pendingCount = $document->approvals()->where('status', ApprovalStatus::Pending)->count();

        if ($pendingCount > 0) {
            return;
        }

        $rejectedCount = $document->approvals()->where('status', ApprovalStatus::Rejected)->count();

        if ($rejectedCount > 0) {
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

    private function ensureDocumentPending(HrDocument $document): void
    {
        if ($document->status !== DocumentStatus::Pending) {
            throw new InvalidArgumentException(
                "Document is not pending (current: {$document->status->value})."
            );
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
