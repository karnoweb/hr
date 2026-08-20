# کارمند

`Employee` هویت HR است و با رابطه polymorphic به User (یا مدل دیگری) وصل می‌شود: `employable_type` + `employable_id`.

## هویت

- `employee_code` یکتا؛ در صورت خالی بودن از `SequenceGenerator` و `hr.employee_code` ساخته می‌شود.
- اگر `sequence_per_branch` فعال باشد، فرمت **باید** `{branch}` داشته باشد.
- کد ملی و شماره بیمه اختیاری‌اند؛ اعتبار کد ملی ایران در سرویس اجباری نیست (`IranianNationalId` برای اپ میزبان).

## وضعیت‌ها

`active` → `suspended` → `active`، یا `terminated` → `active` (reactivate).

تغییر مستقیم `status` روی مدل ممنوع است و `InvalidEmployeeLifecycleException` می‌دهد. فقط سرویس lifecycle مجاز است.

## خاتمه

`terminate()` در یک تراکنش:

- کارمند: `terminated` + `termination_date`
- قرارداد فعال → terminated
- سمت باز و حقوق جاری بسته می‌شوند
- مرخصی/مأموریت pending → cancelled
- وام pending → cancelled
- وام active → سیاست `hr.loan.termination_policy` (پیش‌فرض: `receivable`)
- تأییدهای pending اسناد مربوط → skipped
- مانده مرخصی طبق `hr.leave.termination.balance_policy`

`reactivate()` همان `employee_code` را نگه می‌دارد و قرارداد/سمت/حقوق را دوباره نمی‌سازد.

**آینده:** Offboarding کامل (تسویه نهایی، خروج دارایی، لغو دسترسی) در [future.md](future.md).
