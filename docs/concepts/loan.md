# وام

وام یک اصل (`amount`) است که به اقساط تبدیل می‌شود و از حقوق ماهانه کسر می‌گردد.

## چرخه

`pending` → `active` (با schedule) یا `cancelled`  
`active` → `completed` (پرداخت کامل / تسویه زودهنگام) یا `receivable` (خاتمه کارمند)

## قسط

اگر `installment_amount` دستی باشد باید:

- مثبت باشد
- قسط آخر مثبت بماند
- `installment × n` اصل را پوشش دهد (با تحمل ۰٫۰۱ برای گرد کردن)

در غیر این صورت سیستم `amount / installments` را می‌گذارد و باقیمانده روی قسط آخر می‌رود.

کسر از حقوق فقط برای وام `active` و قسط `pending` با `due_date` داخل period است. ثبت پرداخت واقعی در `payroll()->approve()` است، نه در `calculate()`.

## خاتمه کارمند

`hr.loan.termination_policy`:

| مقدار | معنی |
|--------|------|
| `mark_receivable` | پیش‌فرض — بدهی صریح می‌ماند، دیگر از payroll کسر نمی‌شود |
| `leave_active` | رفتار قبلی؛ وام active دست‌نخورده |
| `require_settlement` | terminate رد می‌شود تا وام تسویه شود |

**آینده:** تسویه نهایی در آخرین حقوق، وصول خارجی، و ادامه receivable در حسابداری — orchestration با event است.
