# سازمان

دپارتمان، سمت و شیفت **داده پایه‌اند** و سرویس دامنه جدا ندارند. ساخت Eloquent برای seed / ادمین مجاز است. انتساب به کارمند را از سرویس بزنید.

```php
use Karnoweb\Hr\Models\Department;
use Karnoweb\Hr\Models\Position;

$it = Department::create([
    'code' => 'IT',
    'name' => 'فناوری اطلاعات',
    'parent_id' => null,
    'is_active' => true,
]);

$dev = Department::create([
    'code' => 'DEV',
    'name' => 'توسعه',
    'parent_id' => $it->id,
    'is_active' => true,
]);

$position = Position::create([
    'code' => 'DEV-SENIOR',
    'name' => 'توسعه‌دهنده ارشد',
    'grade' => 5,
    'is_active' => true,
]);

Hr::employees()->assignPosition($employee, $dev->id, $position->id, '2026-01-01', [
    'is_primary' => true,
]);

$employee->load('currentPosition.department', 'currentPosition.position');
```

برای department-head در workflow، `head_employee_id` دپارتمان را پر کنید.

## قوانین

- دپارتمان نمی‌تواند والد خودش یا نواده‌اش باشد.
- کد دپارتمان/سمت در محدوده شعبه یکتا است (بعد از migration scope کدها).
- هر کارمند حداکثر یک سمت جاری.

## خطاها

| استثنا | کی |
|--------|-----|
| `InvalidOrganizationStructureException` | حلقه درخت، والد نامعتبر |
| `DuplicateActiveRecordException` | دو سمت جاری |

## نتیجه ذخیره‌شده

- `hr_departments`: `path`, `level` بعد از create/update والد به‌روز می‌شود.
- `hr_positions`: کاتالوگ سمت.
- `hr_employee_positions`: تاریخچه؛ جاری بودن با `current_key`.
