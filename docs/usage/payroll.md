# Payroll

```php
$period = Hr::payroll()->openPeriod($branchId, 2026, 3);
Hr::payroll()->calculate($period);
Hr::payroll()->approve($period, approvedBy: $userId);
Hr::payroll()->markPaid($period);
```

نرخ بیمه/مالیات نسخه‌دار:

```bash
php artisan hr:import-rates /path/to/rates.json
php artisan hr:import-rates /path/to/rates.json --dry-run
php artisan hr:import-rates /path/to/rates.json --force
```

```json
{
  "insurance": {
    "effective_date": "2027-03-21",
    "employee_rate": 7,
    "employer_rate": 20,
    "unemployment_rate": 3,
    "ceiling_multiplier": 7,
    "minimum_wage": 53304000
  },
  "tax": {
    "fiscal_year": 1406,
    "effective_date": "2027-03-21",
    "annual_exemption": 672000000,
    "brackets": [
      {"up_to": 200000000, "rate": 10},
      {"up_to": null, "rate": 20}
    ]
  }
}
```

## قوانین

- یک دوره به ازای شعبه + سال + ماه.
- `calculate` فقط در وضعیتی که `canCalculate` / `canEdit` باشد؛ کارمندان `active` همان شعبه.
- حقوق مؤثر period + `hr.payroll.salary_proration` (`calendar_days` پیش‌فرض).
- مرخصی/مأموریت: فقط سهم داخل period.
- اضافه‌کار: فقط approved داخل بازه.
- بیمه: `InsuranceRate::forDate`؛ سقف از `minimum_wage` ردیف نرخ (fallback: `hr.payroll.minimum_wage`).
- مالیات: `hr.tax.method` — `monthly_annualization` یا `ytd_reconciliation`.
- معافیت فردی روی Employee؛ معافیت عائله فقط اگر `hr.tax.dependents_exemption.enabled`.
- کسر وام در `approve` روی اقساط داخل `calculation_log.loan_payments` ثبت می‌شود، نه در calculate.
- `markPaid` فقط بعد از approved.

## خطاها

| استثنا | کی |
|--------|-----|
| `PayrollPeriodExistsException` | دوره تکراری |
| `PayrollPeriodLockedException` | calculate/approve/edit در وضعیت قفل؛ markPaid قبل از approve |
| `InvalidArgumentException` | نبود حقوق هم‌پوشان period؛ نبود نرخ بیمه/پلکان مالیات برای تاریخ |

## نتیجه ذخیره‌شده

`hr_payroll_periods`: `year`, `month`, `start_date`, `end_date`, `working_days`, `status`, `calculated_at`, `approved_at`, `approved_by`, `paid_at`.

`hr_payroll_records` به ازای هر کارمند (idempotent):

| گروه | فیلدها |
|------|--------|
| حضور | `working_days`, `present_days`, `absent_days`, `late_minutes`, `early_leave_minutes` |
| زمان | `leave_days_paid`, `leave_days_unpaid`, `mission_days`, `overtime_*_minutes` |
| پول | `base_salary`, `earnings`, `deductions`, `gross_earnings`, `total_deductions`, `gross_salary` |
| قانونی | `insurance_*`, `taxable_income`, `tax` |
| خالص | `loan_deduction`, `net_salary`, `payable` |
| وضعیت | `status` (calculated → approved → paid) |

`calculation_log` (اسنپ‌شات):

```
hourly_rate
insurance: نرخ، سقف، minimum_wage، insurance_rate_id
tax: method، tax_bracket_id، fiscal_year، ytd_*
loan_payments: [{id, amount, installment_number}]
salary_segments: [{employee_salary_id, from, to, days, weight, base_salary}]
leave_allocation / mission_allocation
policy: salary_resolution, salary_proration, tax_method, daily_work_minutes
```

بعد از approve/paid رویدادهای حسابداری — [مفاهیم حسابداری](../concepts/accounting.md).
