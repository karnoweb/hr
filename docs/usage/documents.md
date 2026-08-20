# سند و گردش کار

```php
use Karnoweb\Hr\Enums\DocumentType;

$doc = Hr::documents()->create(DocumentType::Leave, $employee, [
    'leave_request_id' => $request->id,
    'days' => 3,
], [
    'created_by' => auth()->id(),
    'branch_id' => $employee->branch_id,
]);

Hr::documents()->submit($doc, actorId: auth()->id());
Hr::documents()->approve($approval, 'تأیید شد', actorId: $userId);
Hr::documents()->reject($approval, 'دلیل رد', actorId: $userId);
Hr::documents()->cancel($pendingDocument, actorId: $userId, reason: '...');
Hr::documents()->resubmit($rejectedDocument);

if ($document->canEdit()) {
    $document->ensureEditable();
    $document->update([...]);
}
```

`actorId` وقتی auth وب ندارید الزامی است.

Timeout:

```bash
php artisan hr:process-workflow-timeouts
```

## قوانین

- انواع داخل `hr.documents.require_approval` باید workflow فعال و قابل resolve داشته باشند.
- ویرایش فقط draft؛ وگرنه `DocumentLockedException`.
- `submit` approverها را می‌سازد؛ sequential فقط order اول را فعال می‌کند.
- order فقط-optional بعد از resolve همه مراحلش، order بعدی را باز می‌کند.
- سند وقتی approve می‌شود که required pending نمانده و در sequential همه orderها تمام شده باشند.
- `approve`/`reject` فقط اگر `actorId === assigned_to`.
- مرحله با `can_reject=false` رد نمی‌شود.
- `resubmit` فقط rejected؛ metadata.resubmitted_from پر می‌شود.
- `cancel` فقط pending.
- بعد از approve، اگر `auto_lock_after_approval`، `locked_at = now + lock_delay_hours`.
- `hr.workflow.auto_approve_own_department` اگر true باشد مرحله خود واحد auto-approve می‌شود.

## خطاها

| استثنا | کی |
|--------|-----|
| `UnresolvableWorkflowException` | نوع نیازمند تأیید بدون workflow مناسب |
| `UnresolvableApproverException` | نتوان approver را به user id رساند |
| `UnauthorizedApprovalException` | actor ≠ assigned_to |
| `DocumentLockedException` | ویرایش خارج از draft |
| `InvalidArgumentException` | resubmit غیر rejected؛ cancel غیر pending؛ approval غیر pending؛ مرحله بدون حق رد؛ branch ناسازگار |

## نتیجه ذخیره‌شده

`hr_documents`: `document_number`, `type`, `status`, `effective_date`, `payload` (metadata)، `branch_id`, `created_by`, `approved_by`, `approved_at`, `locked_at`.

`hr_document_approvals`: `workflow_step_id`, `assigned_to`, `status` (pending/approved/rejected/skipped), `comment`, `acted_at`, `deadline_at`.

`hr_document_histories`: هر submit/approve/reject/cancel.

`hr_document_attachments`: فایل‌های پیوست (برای مرخصی استعلاجی لازم است).
