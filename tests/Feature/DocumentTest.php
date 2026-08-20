<?php

namespace Karnoweb\Hr\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Karnoweb\Hr\Enums\ApprovalStatus;
use Karnoweb\Hr\Enums\ApproverType;
use Karnoweb\Hr\Enums\DocumentStatus;
use Karnoweb\Hr\Enums\DocumentType;
use Karnoweb\Hr\Exceptions\UnauthorizedApprovalException;
use Karnoweb\Hr\Exceptions\UnresolvableApproverException;
use Karnoweb\Hr\Exceptions\UnresolvableWorkflowException;
use Karnoweb\Hr\Facades\Hr;
use Karnoweb\Hr\Models\DocumentHistory;
use Karnoweb\Hr\Models\HrDocument;
use Karnoweb\Hr\Models\Workflow;
use Karnoweb\Hr\Models\WorkflowStep;
use Karnoweb\Hr\Services\DocumentService;
use Karnoweb\Hr\Tests\TestCase;

class DocumentTest extends TestCase
{
    public function test_document_numbers_are_unique_under_rapid_creation(): void
    {
        $employee = Hr::employees()->createForUser($this->makeUser(), ['branch_id' => 1]);
        $numbers = [];

        for ($i = 0; $i < 30; $i++) {
            $document = Hr::documents()->create(DocumentType::Leave, $employee, ['index' => $i]);
            $numbers[] = $document->document_number;
        }

        $this->assertCount(30, array_unique($numbers));
    }

    public function test_create_rejects_mismatched_branch_without_override(): void
    {
        $employee = Hr::employees()->createForUser($this->makeUser(), ['branch_id' => 1]);

        $this->expectException(\InvalidArgumentException::class);

        Hr::documents()->create(DocumentType::Leave, $employee, [], ['branch_id' => 99]);
    }

    public function test_create_allows_mismatched_branch_with_explicit_override(): void
    {
        $employee = Hr::employees()->createForUser($this->makeUser(), ['branch_id' => 1]);

        $document = Hr::documents()->create(
            DocumentType::Leave,
            $employee,
            [],
            ['branch_id' => 99, 'allow_branch_override' => true]
        );

        $this->assertSame(99, $document->branch_id);
    }

    public function test_submit_without_workflow_throws_and_leaves_document_draft(): void
    {
        config(['hr.workflow.skip_on_no_approver' => false]);

        $document = $this->draftDocument();

        try {
            Hr::documents()->submit($document);
            $this->fail('Expected UnresolvableWorkflowException.');
        } catch (UnresolvableWorkflowException) {
            // expected
        }

        $this->assertSame(DocumentStatus::Draft, $document->fresh()->status);
        $this->assertSame(0, $document->approvals()->count());
    }

    public function test_submit_without_workflow_auto_approves_when_configured(): void
    {
        config(['hr.workflow.skip_on_no_approver' => true]);

        $document = $this->draftDocument();
        $approver = $this->makeUser();

        $submitted = Hr::documents()->submit($document, actorId: $approver->id);

        $this->assertSame(DocumentStatus::Approved, $submitted->status);
        $this->assertSame($approver->id, $submitted->approved_by);
        $this->assertSame(0, $submitted->approvals()->count());
    }

    public function test_submit_creates_approvals_atomically(): void
    {
        $employee = Hr::employees()->createForUser($this->makeUser(), ['branch_id' => 1]);
        $workflow = $this->workflowFor(DocumentType::Leave, approverIds: [11, 22]);
        $document = Hr::documents()->create(DocumentType::Leave, $employee);

        Hr::documents()->submit($document, actorId: 1);

        $this->assertSame(DocumentStatus::Pending, $document->fresh()->status);
        $this->assertSame(2, $document->approvals()->count());
        $this->assertSame(1, DocumentHistory::query()->where('hr_document_id', $document->id)->count());
        $this->assertNotNull($workflow->id);
    }

    public function test_submit_throws_before_persisting_when_approver_unresolvable(): void
    {
        $employee = Hr::employees()->createForUser($this->makeUser(), ['branch_id' => 1]);

        $workflow = Workflow::query()->create([
            'branch_id' => 1,
            'name' => 'Broken head',
            'document_type' => DocumentType::Leave->value,
            'is_active' => true,
            'priority' => 10,
        ]);

        WorkflowStep::query()->create([
            'workflow_id' => $workflow->id,
            'order' => 1,
            'name' => 'Head',
            'approver_type' => ApproverType::DepartmentHead,
        ]);

        $document = Hr::documents()->create(DocumentType::Leave, $employee);

        try {
            Hr::documents()->submit($document);
            $this->fail('Expected UnresolvableApproverException.');
        } catch (UnresolvableApproverException) {
            // expected
        }

        $this->assertSame(DocumentStatus::Draft, $document->fresh()->status);
        $this->assertSame(0, $document->approvals()->count());
    }

    public function test_approve_completes_workflow_and_records_actor(): void
    {
        $employee = Hr::employees()->createForUser($this->makeUser(), ['branch_id' => 1]);
        $this->workflowFor(DocumentType::Leave, approverIds: [101]);
        $document = Hr::documents()->create(DocumentType::Leave, $employee);
        Hr::documents()->submit($document, actorId: 1);

        $approval = $document->approvals()->first();
        Hr::documents()->approve($approval, 'ok', actorId: 101);

        $document->refresh();
        $this->assertSame(DocumentStatus::Approved, $document->status);
        $this->assertSame(101, (int) $document->approved_by);
    }

    public function test_reject_marks_sibling_approvals_skipped(): void
    {
        $employee = Hr::employees()->createForUser($this->makeUser(), ['branch_id' => 1]);
        $this->workflowFor(DocumentType::Leave, approverIds: [201, 202]);
        $document = Hr::documents()->create(DocumentType::Leave, $employee);
        Hr::documents()->submit($document, actorId: 1);

        $first = $document->approvals()->orderBy('id')->first();
        $second = $document->approvals()->orderByDesc('id')->first();

        Hr::documents()->reject($first, 'no', actorId: 201);

        $this->assertSame(DocumentStatus::Rejected, $document->fresh()->status);
        $this->assertSame(ApprovalStatus::Rejected, $first->fresh()->status);
        $this->assertSame(ApprovalStatus::Skipped, $second->fresh()->status);
    }

    public function test_approve_after_reject_throws(): void
    {
        $employee = Hr::employees()->createForUser($this->makeUser(), ['branch_id' => 1]);
        $this->workflowFor(DocumentType::Leave, approverIds: [301, 302]);
        $document = Hr::documents()->create(DocumentType::Leave, $employee);
        Hr::documents()->submit($document, actorId: 1);

        $rejectApproval = $document->approvals()->orderBy('id')->first();
        $otherApproval = $document->approvals()->orderByDesc('id')->first();

        Hr::documents()->reject($rejectApproval, 'no', actorId: 301);

        $this->expectException(\InvalidArgumentException::class);
        Hr::documents()->approve($otherApproval, null, actorId: 302);
    }

    public function test_approve_throws_when_actor_does_not_match_assignment(): void
    {
        $employee = Hr::employees()->createForUser($this->makeUser(), ['branch_id' => 1]);
        $this->workflowFor(DocumentType::Leave, approverIds: [401]);
        $document = Hr::documents()->create(DocumentType::Leave, $employee);
        Hr::documents()->submit($document, actorId: 1);

        $approval = $document->approvals()->first();

        $this->expectException(UnauthorizedApprovalException::class);
        Hr::documents()->approve($approval, null, actorId: 999);
    }

    public function test_reject_requires_actor_id(): void
    {
        $employee = Hr::employees()->createForUser($this->makeUser(), ['branch_id' => 1]);
        $this->workflowFor(DocumentType::Leave, approverIds: [501]);
        $document = Hr::documents()->create(DocumentType::Leave, $employee);
        Hr::documents()->submit($document, actorId: 1);

        $approval = $document->approvals()->first();

        $this->expectException(UnauthorizedApprovalException::class);
        Hr::documents()->reject($approval, 'no', actorId: null);
    }

    public function test_resubmit_clones_rejected_document_to_new_draft(): void
    {
        $employee = Hr::employees()->createForUser($this->makeUser(), ['branch_id' => 1]);
        $this->workflowFor(DocumentType::Leave, approverIds: [601]);
        $document = Hr::documents()->create(DocumentType::Leave, $employee, ['days' => 2]);
        Hr::documents()->submit($document, actorId: 1);
        Hr::documents()->reject($document->approvals()->first(), 'retry later', actorId: 601);

        $resubmitted = Hr::documents()->resubmit($document->fresh(), actorId: 7);

        $this->assertNotSame($document->id, $resubmitted->id);
        $this->assertSame(DocumentStatus::Draft, $resubmitted->status);
        $this->assertSame(2, $resubmitted->getData('days'));
        $this->assertSame($document->id, $resubmitted->metadata['resubmitted_from']);
    }

    public function test_document_service_is_container_singleton(): void
    {
        $this->assertSame(
            $this->app->make(DocumentService::class),
            Hr::documents()
        );
    }

    public function test_concurrent_document_number_allocation_remains_unique(): void
    {
        $employee = Hr::employees()->createForUser($this->makeUser(), ['branch_id' => 1]);
        $numbers = DB::transaction(function () use ($employee) {
            $allocated = [];

            for ($i = 0; $i < 25; $i++) {
                $allocated[] = DB::transaction(function () use ($employee, $i) {
                    return Hr::documents()->create(DocumentType::Leave, $employee, ['n' => $i])->document_number;
                });
            }

            return $allocated;
        });

        sort($numbers);

        $this->assertCount(25, array_unique($numbers));
    }

    protected function draftDocument(): HrDocument
    {
        $employee = Hr::employees()->createForUser($this->makeUser(), ['branch_id' => 1]);

        return Hr::documents()->create(DocumentType::Leave, $employee);
    }

    /**
     * @param  list<int|null>  $approverIds
     */
    protected function workflowFor(DocumentType $type, array $approverIds): Workflow
    {
        $workflow = Workflow::query()->create([
            'branch_id' => 1,
            'name' => 'Test workflow',
            'document_type' => $type->value,
            'is_active' => true,
            'priority' => 10,
        ]);

        foreach ($approverIds as $index => $approverId) {
            WorkflowStep::query()->create([
                'workflow_id' => $workflow->id,
                'order' => $index + 1,
                'name' => 'Step '.($index + 1),
                'approver_type' => ApproverType::User,
                'approver_id' => $approverId,
            ]);
        }

        return $workflow;
    }
}
