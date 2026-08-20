# قرارداد

```php
use Karnoweb\Hr\Enums\ContractType;

$contract = Hr::contracts()->hire($employee, [
    'contract_number' => 'C-1403-001',
    'type' => ContractType::Permanent,
    'start_date' => '2026-01-01',
    'hr_document_id' => $documentId,
]);

$new = Hr::contracts()->renew($employee, [
    'contract_number' => 'C-1404-001',
    'start_date' => '2027-01-01',
]);

Hr::contracts()->extend($employee, '2028-12-31');
Hr::contracts()->terminate($employee, now());

$current = $employee->currentContract;
```

## قوانین

- حداکثر یک قرارداد فعال (`active_key`).
- `contract_number` سراسری یکتا است.
- `end_date` نباید قبل از `start_date` باشد.
- `hire` اگر قرارداد فعال وجود داشته باشد شکست می‌خورد — از `renew` استفاده کنید.
- `hr_document_id` در صورت ارسال باید سند معتبر باشد.

## خطاها

| استثنا | کی |
|--------|-----|
| `DuplicateActiveRecordException` | تلاش برای دو قرارداد فعال |
| `InvalidArgumentException` | نبود قرارداد فعال برای renew/extend/terminate؛ تاریخ پایان نامعتبر |

## نتیجه ذخیره‌شده

`hr_contracts`: `type`, `status` (`active` / `ended` / `terminated`), بازه تاریخ، `active_key` فقط روی ردیف فعال، `hr_document_id` اختیاری.

`renew` قرارداد قبلی را می‌بندد و ردیف جدید فعال می‌سازد (یک تراکنش).
