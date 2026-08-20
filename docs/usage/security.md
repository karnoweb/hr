# امنیت و شعبه

پکیج لایه دامنه است. بیشتر سرویس‌ها چک نمی‌کنند caller کیست یا به کدام شعبه دسترسی دارد.

## قوانین

- قبل از expose API، در controller/policy/middleware مجوز و فیلتر شعبه بگذارید.
- هیچ Global Scope شعبه‌ای پیش‌فرض نیست. بعضی مدل‌ها `scopeForBranch()` دارند (opt-in).
- تنها مجوز داخل پکیج: `DocumentService::approve/reject` و برابری `actorId` با `assigned_to`.
- rate limit برای مرخصی/وام/مأموریت داخل پکیج نیست؛ از throttle لاراول استفاده کنید.

## مدل‌هایی که `branch_id` دارند

| حوزه | ستون | اقدام میزبان |
|------|------|----------------|
| Employee, Department, Position, Shift | `branch_id` | Scope/Policy روی CRUD |
| HrDocument, Workflow, PayrollPeriod | `branch_id` | محدود کردن submit/محاسبه |
| Attendance, Leave, Mission, Loan, Contract, Salary | از طریق `employee_id` | فیلتر از `employee.branch_id` |

## خطاها

| استثنا | کی |
|--------|-----|
| `UnauthorizedApprovalException` | تأیید/رد سند توسط فرد غیراسااین‌شده |
| `InvalidArgumentException` | branch سند با کارمند ناسازگار است |

## نتیجه ذخیره‌شده

پکیج جدول permission نمی‌سازد. ردپای عمل سند در `hr_document_histories` و فیلدهای `acted_at` / `approved_by` است.
