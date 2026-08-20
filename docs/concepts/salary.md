# حقوق

حقوق کارمند یک **تاریخچه مؤثر** است، نه فقط یک عدد جاری.

## رکورد حقوق

`EmployeeSalary`: `base_salary` + ساختار اختیاری + override آیتم‌ها + `effective_date` / `end_date`.

حداکثر یک حقوق `is_current`. تغییر حقوق (`changeSalary`) قبلی را می‌بندد و جدید را از `effective_date` باز می‌کند.

## آیتم و محاسبه

`SalaryItem`: `fixed` / `percentage` / `formula`. گراف وابستگی با تشخیص دور. وابستگی تو در تو (`A → B → C`) کامل expand می‌شود.

اولویت مقدار fixed: override کارمند → مقدار ساختار → `default_value` آیتم.

## اثر در Payroll

Payroll حقوق **هم‌پوشان با period** را می‌گیرد (نه فقط current) و طبق `hr.payroll.salary_proration` prorate می‌کند:

- `calendar_days` (پیش‌فرض)
- `working_days`
- `none` — حقوق در پایان period بدون تسهیم

**آینده:** Effective Dating Engine عمومی برای همه موجودیت‌ها؛ الان فقط حقوق/سمت/قرارداد/شیفت این الگو را دارند.
