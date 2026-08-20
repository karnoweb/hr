# مرخصی

```php
$request = Hr::leave()->request($employee, [
    'type' => 'annual',
    'start_date' => '2026-06-01',
    'end_date' => '2026-06-05',
    'reason' => 'سفر خانوادگی',
], ['create_document' => true]);

Hr::leave()->approve($request);
Hr::leave()->reject($request, 'دلیل رد');
Hr::leave()->cancel($request);

$balance = Hr::leave()->balance($employee, 2026, 'annual');
```

روزها پیش‌فرض از `WorkingDayCalculator` است. برای مقدار دستی: `days` بدهید یا `use_calculated_days => false`.

```bash
php artisan hr:carry-over-leave-balances {fromYear} {toYear}
```

## قوانین

- `type` باید در `hr.leave.types` باشد.
- `end_date >= start_date`.
- همپوشانی با مرخصی pending/approved ممنوع؛ بازه مجاور مجاز.
- نوع با مانده روز: درخواست، روزهای هر سال را جدا رزرو می‌کند (بازه بین‌سال تقسیم می‌شود).
- `hourly`: سقف `max_hours_per_month` روی مجموع pending+approved همان ماه. در approve کارمند lock می‌شود.
- `fixed_days`: تعداد روز باید دقیقاً همان باشد.
- `once_per_employment`: فقط یک pending/approved در کل استخدام.
- `requires_document`: قبل از approve باید `hr_document_id` و حداقل یک attachment باشد.
- approve فقط pending؛ فقط نوع paid با مانده، مانده را کم می‌کند.
- cancel مرخصی approved فقط اگر هنوز شروع نشده باشد؛ مانده برمی‌گردد و حضور leave برمی‌گردد.
- خاتمه کارمند: مانده طبق `hr.leave.termination.balance_policy`.

## خطاها

| استثنا | کی |
|--------|-----|
| `InvalidArgumentException` | نوع ناشناخته؛ بازه نامعتبر؛ overlap؛ سقف ساعتی؛ fixed_days؛ once_per_employment؛ سند/پیوست لازم؛ وضعیت غلط approve/reject/cancel |
| `InsufficientLeaveBalanceException` | مانده سال (بعد از رزرو pending) کافی نیست |

## نتیجه ذخیره‌شده

`hr_leave_requests`:

| فیلد | معنی |
|------|------|
| `type`, `start_date`, `end_date` | بازه و نوع |
| `days`, `hours` | کل درخواست (نه سهم یک ماه) |
| `status` | pending / approved / rejected / cancelled |
| `hr_document_id` | اگر `create_document` یا دستی |
| `reason` | دلیل / متن رد |

`hr_leave_balances`: `entitled_days`, `used_days`, `carried_days`, `adjustment_days`, `remaining_days`, `notes`.

بعد از approve، برای روزهای کاری بازه، `hr_attendance_records.status = leave`.
