# اضافه‌کار

مسیر اصلی از حضور است، نه insert دستی.

```php
Hr::attendance()->clockOut($employee, $at, ['hr_document_id' => $preApprovalDocId]);

Hr::overtime()->approve($record, $approvedByUserId);
Hr::overtime()->reject($record, 'دلیل رد');

$totals = Hr::overtime()->approvedMinutesForPeriod($employee, $payrollPeriod);
// ['regular' => 120, 'night' => 45, 'holiday' => 0]
```

`hr.overtime`: `min_minutes`, `monthly_cap` (دقیقه), `requires_pre_approval`, `night_start` / `night_end`, `rates`.

## قوانین

- اول باید clock-out شده باشد.
- زیر `min_minutes` رکورد ساخته/به‌روز نمی‌شود (pending قبلی همان نوع پاک می‌شود).
- approve فقط pending؛ سقف ماه روی مجموع approved است.
- قبل از cap، employee lock می‌شود.
- اگر `requires_pre_approval`: سند `overtime_approval` تأییدشده با `effective_date <=` تاریخ کار.

## خطاها

| استثنا | کی |
|--------|-----|
| `InvalidArgumentException` | بدون clock-out؛ وضعیت غیر pending؛ عبور از سقف ماه؛ نبود/نوع غلط/تأییدنشده بودن سند پیش‌تأیید؛ effective_date بعد از تاریخ کار |

## نتیجه ذخیره‌شده

`hr_overtime_records`:

| فیلد | معنی |
|------|------|
| `type` | regular / night / holiday |
| `calculated_minutes` | از classifier |
| `approved_minutes` | بعد از approve (= calculated) |
| `status` | pending / approved / rejected |
| `approved_by`, `approved_at` | در approve |
| `attendance_record_id`, `hr_document_id` | پیوند |

ستون‌های `overtime_*` روی همان ردیف حضور هم پر می‌شوند.
