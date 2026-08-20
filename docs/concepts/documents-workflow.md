# سند و گردش کار

هر رخداد رسمی HR می‌تواند یک `HrDocument` داشته باشد: استخدام، خاتمه، تغییر سمت/حقوق، مرخصی، مأموریت، وام، تأیید اضافه‌کار و ….

## وضعیت سند

`draft` → `pending` → `approved` / `rejected` / `cancelled`  
پس از approve، در صورت تنظیم، بعد از `lock_delay_hours` قفل می‌شود.

ویرایش محتوا فقط در draft. سند ردشده با `resubmit` به draft جدید برمی‌گردد.

شماره سند concurrency-safe است (`LEA-2026-0001` و مشابه).

## Workflow

برای انواع داخل `hr.documents.require_approval` باید workflow فعال وجود داشته باشد وگرنه submit شکست می‌خورد.

- اجرا: `parallel` (همه مراحل با هم) یا `sequential` (order به order)
- approver: `user` / `department_head` / `position` / `custom`
- شرط مرحله، `is_required`، `can_reject`
- timeout: `hr:process-workflow-timeouts` — auto_approve / auto_reject / skip / escalate

در sequential، order فقط-optional وقتی همه مراحلش resolve شوند کامل است و order بعدی فعال می‌شود. سند تا وقتی همه orderها تمام نشده‌اند approve نمی‌شود.

تأیید مرحله فقط توسط `assigned_to` (`actorId`).

**آینده:** UI انتخاب approver و delegation خارج از پکیج است.
