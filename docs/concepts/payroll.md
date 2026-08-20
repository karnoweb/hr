# حقوق ماهانه (Payroll)

Payroll تجمیع حضور، مرخصی، مأموریت، اضافه‌کار، حقوق مؤثر، بیمه، مالیات و اقساط وام برای یک ماه/شعبه است.

## دوره

`PayrollPeriod`: `draft` → `calculating` → `calculated` → `approved` → `paid` (یا قفل).

یکتایی: شعبه + سال + ماه.

فقط کارمندان `active` همان شعبه محاسبه می‌شوند.

## محاسبه

ورودی‌ها batch preload می‌شوند (`PayrollBatchContext`). برای هر کارمند یک `PayrollRecord` با `updateOrCreate` نوشته می‌شود (idempotent).

حقوق: مؤثر در period + proration.  
مرخصی/مأموریت: فقط روزهای داخل period.  
بیمه: نرخ نسخه‌دار `insurance_rates` (سقف = `minimum_wage` نسخه‌دار × ضریب).  
مالیات: `hr.tax.method` = `monthly_annualization` (پیش‌فرض) یا `ytd_reconciliation`.

`calculation_log` اسنپ‌شات ورودی‌های مؤثر است (segments حقوق، تخصیص مرخصی/مأموریت، نسخه نرخ، policy).

## تأیید و پرداخت

`approve` اقساط لاگ‌شده را پرداخت می‌کند و event `PayrollPeriodApproved` می‌دهد.  
`markPaid` وضعیت را paid می‌کند و `PayrollPeriodPaid` می‌دهد.

**آینده:** موتور قواعد payroll جدا، تسویه نهایی خاتمه، و reconciliation سالانه اجباری — [future.md](future.md).
