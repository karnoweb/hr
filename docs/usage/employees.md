# کارمند

```php
$employee = Hr::employees()->createForUser($user, [
    'branch_id' => 1,
    'hire_date' => now(),
    'national_id' => '0123456789',
    'insurance_number' => '1234567890',
]);

Hr::employees()->assignPosition($employee, $departmentId, $positionId, $effectiveDate, [
    'is_primary' => true,
    'hr_document_id' => $documentId,
]);

Hr::employees()->suspend($employee);
Hr::employees()->terminate($employee, now());
Hr::employees()->reactivate($employee);

$code = Hr::employees()->generateEmployeeCode($branchId);
```

## قوانین

- یک User نمی‌تواند دو Employee داشته باشد (ایندکس یکتا employable).
- `employee_code` یکتا است؛ اگر خالی باشد از sequence ساخته می‌شود.
- با `sequence_per_branch=true` فرمت باید `{branch}` داشته باشد وگرنه `InvalidArgumentException`.
- `status` را مستقیم روی مدل عوض نکنید.
- `termination_date` نباید قبل از `hire_date` باشد.
- `suspend` قرارداد/سمت/حقوق را نمی‌بندد؛ `terminate` می‌بندد.
- `reactivate` همان کد کارمند را نگه می‌دارد و قرارداد/حقوق جدید نمی‌سازد.
- وام active طبق `hr.loan.termination_policy` (پیش‌فرض `receivable`).
- مانده مرخصی طبق `hr.leave.termination.balance_policy` (`forfeit` / `payout` / `carry`).

## خطاها

| استثنا | کی |
|--------|-----|
| `EmployeeAlreadyExistsException` | همین employable قبلاً کارمند دارد |
| `InvalidEmployeeLifecycleException` | terminate تکراری، reactivate از وضعیت غیر terminated، تاریخ خاتمه قبل از استخدام، تغییر مستقیم status، وام‌های تسویه‌نشده وقتی policy=`require_settlement` |
| `InvalidArgumentException` | فرمت کد کارمند ناقص است |
| `DuplicateActiveRecordException` | نقض invariant سمت جاری (مسیر انتساب) |

## نتیجه ذخیره‌شده

جدول `hr_employees`:

- هویت: `employee_code`, `national_id`, `insurance_number`, `branch_id`, `employable_*`
- چرخه: `status`, `hire_date`, `termination_date`
- معافیت payroll: `insurance_exempt`, `tax_exempt`, `additional_tax_exemption`, `dependents_count`

`assignPosition` یک ردیف `hr_employee_positions` با `current_key = employee_id` می‌سازد و سمت قبلی را می‌بندد (`end_date`, `current_key=null`).

`terminate` همان تراکنش سمت‌ها، قراردادها، حقوق جاری، درخواست‌های pending و approverهای pending را به‌روز می‌کند.
