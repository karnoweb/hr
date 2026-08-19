<?php

namespace Karnoweb\Hr\Services;

use Carbon\Carbon;
use Karnoweb\Hr\Enums\ApprovalStatus;
use Karnoweb\Hr\Enums\DocumentStatus;
use Karnoweb\Hr\Enums\DocumentType;
use Karnoweb\Hr\Exceptions\DocumentLockedException;
use Karnoweb\Hr\Models\DocumentApproval;
use Karnoweb\Hr\Models\DocumentHistory;
use Karnoweb\Hr\Models\Employee;
use Karnoweb\Hr\Models\HrDocument;
use Karnoweb\Hr\Models\Workflow;

/**
 * Service for HR documents: create, submit, approve, reject with workflow and history.
 *
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
     * @param  array{branch_id?: int|null, effective_date?: \DateTimeInterface|string, expiry_date?: \DateTimeInterface|string|null, notes?: string|null, created_by?: int|null, metadata?: array|null}  $options  branch_id (defaults to employee branch), effective_date (default now), expiry_date, notes, created_by, metadata.
     * @return HrDocument Created document in Draft status.
     */
    public function create(DocumentType $type, Employee $employee, array $data = [], array $options = []): HrDocument
    {
        $effectiveDate = $options['effective_date'] ?? now();
        if (! $effectiveDate instanceof \DateTimeInterface) {
            $effectiveDate = Carbon::parse($effectiveDate);
        }

        return HrDocument::create([
            'branch_id' => $options['branch_id'] ?? $employee->branch_id,
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
     * Submit a draft document: set status to Pending and create workflow approvals.
     *
     * @param  HrDocument  $document  Document to submit (must be editable / draft).
     * @return HrDocument Updated document (fresh from DB).
     *
     * @throws DocumentLockedException When document is locked (e.g. already approved and locked).
     */
    public function submit(HrDocument $document): HrDocument
    {
        $document->ensureEditable();

        $workflow = Workflow::findForDocument($document);

        $document->update(['status' => DocumentStatus::Pending]);

        $this->recordHistory($document, 'submit', DocumentStatus::Draft, DocumentStatus::Pending, $document->getAttributes());

        if ($workflow) {
            foreach ($workflow->steps as $step) {
                DocumentApproval::create([
                    'hr_document_id' => $document->id,
                    'workflow_step_id' => $step->id,
                    'assigned_to' => $step->approver_id,
                    'status' => ApprovalStatus::Pending,
                    'deadline_at' => $step->timeout_hours ? now()->addHours($step->timeout_hours) : null,
                ]);
            }
        }

        return $document->fresh();
    }

    /**
     * Approve a pending document approval step; may mark document as Approved when workflow is complete.
     *
     * @param  DocumentApproval  $approval  Pending approval to approve.
     * @param  string|null  $comment  Optional comment.
     * @return DocumentApproval Updated approval (fresh from DB).
     *
     * @throws \InvalidArgumentException When approval is not pending.
     */
    public function approve(DocumentApproval $approval, ?string $comment = null): DocumentApproval
    {
        if ($approval->status !== ApprovalStatus::Pending) {
            throw new \InvalidArgumentException('Approval is not pending.');
        }

        $approval->update([
            'status' => ApprovalStatus::Approved,
            'comment' => $comment,
            'acted_at' => now(),
        ]);

        $document = $approval->document;
        $this->recordHistory($document, 'approve_step', DocumentStatus::Pending, DocumentStatus::Pending, [
            'workflow_step_id' => $approval->workflow_step_id,
            'approval_id' => $approval->id,
        ]);

        $this->advanceDocumentStatusIfComplete($document);

        return $approval->fresh();
    }

    /**
     * Reject a pending document approval; sets document status to Rejected.
     *
     * @param  DocumentApproval  $approval  Pending approval to reject.
     * @param  string|null  $comment  Optional comment.
     * @return DocumentApproval Updated approval (fresh from DB).
     *
     * @throws \InvalidArgumentException When approval is not pending.
     */
    public function reject(DocumentApproval $approval, ?string $comment = null): DocumentApproval
    {
        if ($approval->status !== ApprovalStatus::Pending) {
            throw new \InvalidArgumentException('Approval is not pending.');
        }

        $approval->update([
            'status' => ApprovalStatus::Rejected,
            'comment' => $comment,
            'acted_at' => now(),
        ]);

        $document = $approval->document;
        $document->update(['status' => DocumentStatus::Rejected]);
        $this->recordHistory($document, 'reject', DocumentStatus::Pending, DocumentStatus::Rejected, [
            'workflow_step_id' => $approval->workflow_step_id,
            'comment' => $comment,
        ]);

        return $approval->fresh();
    }

    /**
     * If all approvals are done (no pending), set document to Approved or Rejected and optionally lock.
     *
     * @param  HrDocument  $document  Document to advance.
     */
    private function advanceDocumentStatusIfComplete(HrDocument $document): void
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

        $document->update([
            'status' => DocumentStatus::Approved,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        if (config('hr.documents.auto_lock_after_approval', true)) {
            $delayHours = config('hr.documents.lock_delay_hours', 24);
            $document->update([
                'locked_at' => now()->addHours($delayHours),
            ]);
        }

        $this->recordHistory($document, 'approved', DocumentStatus::Pending, DocumentStatus::Approved, []);
    }

    /**
     * Record a document history entry for audit.
     *
     * @param  HrDocument  $document  Document.
     * @param  string  $action  Action name (e.g. submit, approve_step, reject, approved).
     * @param  DocumentStatus|null  $fromStatus  Previous status.
     * @param  DocumentStatus|null  $toStatus  New status.
     * @param  array<string, mixed>  $changes  Additional change data.
     */
    private function recordHistory(
        HrDocument $document,
        string $action,
        ?DocumentStatus $fromStatus,
        ?DocumentStatus $toStatus,
        array $changes
    ): void {
        DocumentHistory::create([
            'hr_document_id' => $document->id,
            'user_id' => auth()->id(),
            'action' => $action,
            'from_status' => $fromStatus?->value,
            'to_status' => $toStatus?->value,
            'changes' => $changes,
        ]);
    }
}
