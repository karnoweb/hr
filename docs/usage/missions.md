# مأموریت

```php
$mission = Hr::missions()->request($employee, [
    'start_date' => '2026-07-01',
    'end_date' => '2026-07-03',
    'destination' => 'اصفهان',
    'purpose' => 'آموزش',
], ['create_document' => true]);

Hr::missions()->approve($mission);
Hr::missions()->reject($mission, 'دلیل');
Hr::missions()->cancel($mission);
```

## قوانین

- `end_date >= start_date`.
- همپوشانی با مأموریت یا مرخصی pending/approved ممنوع.
- approve دوباره overlap و `EmployeeStatus::Active` را چک می‌کند.
- cancel مأموریت approved فقط قبل از شروع.
- Payroll فقط روزهای داخل period را می‌شمارد.

## خطاها

| استثنا | کی |
|--------|-----|
| `InvalidArgumentException` | بازه نامعتبر؛ overlap در request یا approve؛ وضعیت غلط؛ cancel بعد از شروع |
| `InvalidEmployeeLifecycleException` | approve وقتی کارمند active نیست |

## نتیجه ذخیره‌شده

`hr_mission_requests`: `destination`, `purpose`, `start_date`, `end_date`, `days`, `status`, `hr_document_id`.

بعد از approve: حضور روزهای کاری با `status = mission`.
