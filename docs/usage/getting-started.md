# نصب و راه‌اندازی

```bash
composer require karnoweb/hr:^13.1
php artisan vendor:publish --tag=hr-config   # اختیاری
php artisan migrate
```

جداول با پیشوند `hr_` ساخته می‌شوند (`config('hr.tables.prefix')`).

```env
HR_USER_MODEL=App\Models\User
HR_BRANCH_MODEL=App\Models\Branch
HR_CALENDAR_TYPE=jalali
```

## اتصال User

```php
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Karnoweb\Hr\Models\Employee;

class User extends Authenticatable
{
    public function employee(): MorphOne
    {
        return $this->morphOne(Employee::class, 'employable');
    }
}
```

```php
$employee = Hr::employees()->createForUser($user, [
    'branch_id' => 1,
    'hire_date' => now(),
    'national_id' => '0123456789',
]);

$employee = Hr::employees()->findByUser($user);
$user = $employee->employable;
```

کد ملی اجباری نیست. برای اعتبارسنجی در فرم:

```php
use Karnoweb\Hr\Support\IranianNationalId;

IranianNationalId::isValid('0123456789');
$request->validate(['national_id' => ['required', new IranianNationalId]]);
```

## تنظیمات پرکاربرد

```php
Hr::config('leave.types.annual.days_per_year');
Hr::config('overtime.rates.regular');
```

کلیدهای مهم `config/hr.php`: تقویم، انواع مرخصی، اضافه‌کار، بیمه/مالیات، وام، payroll، سند، workflow، dispatch حسابداری.

## دستورها

| دستور | نقش |
|--------|------|
| `hr:auto-clock-out` | خروج خودکار (اگر در config فعال باشد) — hourly |
| `hr:carry-over-leave-balances` | انتقال مانده سال |
| `hr:import-rates` | وارد کردن نرخ بیمه/مالیات JSON |
| `hr:process-workflow-timeouts` | timeout گردش کار — هر ۵ دقیقه |

## قوانین

- عملیات حاکم را با `Hr::` انجام دهید، نه `Model::create()` خام.
- پکیج authorization و فیلتر شعبه ندارد؛ قبل از API عمومی Policy بگذارید — [security.md](security.md).

## خطاها

خطاهای نصب معمولاً از خود لاراول‌اند (migration، config). خطاهای دامنه در [errors.md](errors.md).

## نتیجه ذخیره‌شده

بعد از migrate جداول `hr_*` ساخته می‌شوند و در صورت وجود، یک ردیف seed بیمه/مالیات با برچسب NEEDS VERIFICATION.
