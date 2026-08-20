# حضور و شیفت

```php
use Karnoweb\Hr\Models\Shift;

$shift = Shift::create([
    'code' => 'MORNING',
    'name' => 'صبح',
    'start_time' => '08:00',
    'end_time' => '16:00',
    'break_start' => '12:00',
    'break_end' => '13:00',
    'work_minutes' => 420,
    'is_active' => true,
]);

Hr::shiftAssignments()->assignShift($employee, $shift->id, '2026-01-01');
Hr::shiftAssignments()->assignPattern($employee, $patternId, $effectiveDate, $patternStartDate);

Hr::attendance()->clockIn($employee);
Hr::attendance()->clockOut($employee);
Hr::attendance()->ensureDayRecord($employee, $date);
Hr::attendance()->adjust($employee, $date, ['late_minutes' => 0], $userId);
```

`clockOut` در صورت عبور از `min_minutes` رکورد اضافه‌کار pending می‌سازد.

## قوانین

- `date` = تاریخ clock-in؛ خروج بعد از نیمه‌شب همان ردیف است.
- در یک تاریخ نمی‌توان دو بار clock-in باز داشت.
- clock-out باید بعد از clock-in باشد.
- تأخیر/تعجیل از شیفت resolve‌شده و `hr.attendance.*_tolerance_minutes` است.
- فقط یک انتساب شیفت جاری.
- `adjust` فقط فیلدهای حضور معتبر را عوض می‌کند و در `raw_data` audit می‌نویسد.

## خطاها

| استثنا | کی |
|--------|-----|
| `InvalidArgumentException` | قبلاً clock-in شده؛ clock-out بدون ورود باز؛ زمان خروج قبل از ورود؛ نبود رکورد برای adjust؛ فیلد adjust خالی؛ نبود shift_id و pattern همزمان |
| `HrException` | اصلاح وقتی سیاست/وضعیت اجازه نمی‌دهد (مثلاً نیاز به approval اگر در config باشد) |
| `DuplicateActiveRecordException` | دو انتساب شیفت جاری |

## نتیجه ذخیره‌شده

`hr_attendance_records` برای هر روز کارمند:

- `clock_in`, `clock_out`, `date`, `status`
- `late_minutes`, `early_leave_minutes`, دقایق کار
- `overtime_minutes`, `overtime_night_minutes`, `overtime_holiday_minutes`
- `raw_data` برای اصلاح دستی
- `source` (مثلاً `manual`)

`hr_employee_shift_assignments`: شیفت یا الگو، `effective_date` / `end_date`, `current_key`.
