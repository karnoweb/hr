# اضافه‌کار

اضافه‌کار از اختلاف خروج با پایان شیفت به‌دست می‌آید، نه از ورود دستی دقایق (مسیر اصلی: `clockOut` → classify → `OvertimeRecord`).

## طبقه‌بندی

`OvertimeMinuteClassifier` دقایق را به `regular` / `night` / `holiday` می‌شکند. پنجره شب از `hr.overtime.night_start` / `night_end`.

ضریب هر نوع از `OvertimeType::rate()` و `hr.overtime.rates` است.

## سقف و پیش‌تأیید

- `min_minutes`: زیر آستانه رکورد ساخته نمی‌شود.
- `monthly_cap`: مجموع دقایق approved در ماه تقویمی.
- `requires_pre_approval`: نیاز به سند `overtime_approval` تأییدشده با `effective_date` ≤ تاریخ کار.

Approve کارمند را lock می‌کند تا دو approve همزمان سقف را رد نکنند.

Payroll فقط اضافه‌کار approved داخل period را به درآمد تبدیل می‌کند.
