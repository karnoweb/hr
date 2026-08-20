# حقوق

```php
use Karnoweb\Hr\Models\SalaryItem;
use Karnoweb\Hr\Enums\SalaryItemType;
use Karnoweb\Hr\Enums\CalculationType;

SalaryItem::create([
    'code' => 'HOUSING',
    'name' => 'حق مسکن',
    'type' => SalaryItemType::Earning,
    'calculation_type' => CalculationType::Fixed,
    'default_value' => 3_000_000,
    'is_taxable' => true,
    'is_insurable' => true,
]);

Hr::salaries()->assign($employee, [
    'base_salary' => 50_000_000,
    'salary_structure_id' => $structure->id,
    'effective_date' => '2026-01-01',
    'items' => [
        ['code' => 'HOUSING', 'value' => 3_000_000],
    ],
]);

Hr::salaries()->changeSalary($employee, [
    'base_salary' => 55_000_000,
    'effective_date' => '2026-04-01',
    'hr_document_id' => $salaryChangeDocumentId,
]);

$result = Hr::salaries()->calculate($employee->currentSalary);
$current = Hr::salaries()->currentSalary($employee);
$inPeriod = Hr::salaries()->salariesForPeriod($employee, $from, $to);
```

آیتم و ساختار داده پایه‌اند (seed/ادمین). تخصیص به کارمند فقط از سرویس.

## قوانین

- `base_salary` الزامی است.
- حداکثر یک حقوق جاری (`current_key`).
- دومین تخصیص باید `changeSalary` باشد.
- `percentage_of` و فرمول باید به `code` موجود برسند؛ وابستگی تو در تو expand می‌شود؛ دور ممنوع است.
- اولویت fixed: override کارمند → ساختار → default آیتم.
- Payroll از `salariesForPeriod` + proration استفاده می‌کند، نه لزوماً current.

## خطاها

| استثنا | کی |
|--------|-----|
| `DuplicateActiveRecordException` | دو حقوق جاری |
| `InvalidArgumentException` | نبود حقوق جاری برای change؛ نبود base_salary؛ آیتم بدون id/code+value؛ dependency نامعتبر یا circular |

## نتیجه ذخیره‌شده

`hr_employee_salaries`: `base_salary`, `effective_date`, `end_date`, `is_current`, `current_key`, `salary_structure_id`, `hr_document_id`.

`hr_employee_salary_items`: override مقدار به ازای `salary_item_id`.

خروجی `calculate()` آرایه است (persist نمی‌شود مگر داخل payroll):

```
base_salary
items[]: code, name, type, calculation_type, amount, is_taxable, is_insurable
totals: earnings, deductions, taxable_amount, insurable_amount, gross_earnings, net_before_statutory
```
