# وام

```php
$loan = Hr::loans()->apply($employee, [
    'amount' => 50_000_000,
    'installments' => 12,
    'start_date' => '2026-03-01',
    'purpose' => 'ضروری',
]);

Hr::loans()->approve($loan);
Hr::loans()->reject($loan, 'دلیل رد');
Hr::loans()->recordPayment($installment, paidDate: '2026-04-01');
Hr::loans()->settleEarly($loan, '2026-06-01');

$due = Hr::loans()->deductionsForPeriod($employee, $payrollPeriod);
```

`hr.loan`: `enabled`, `max_amount`, `max_installments`, `min_installments`, `max_active_loans`, `min_months_between_loans`, `max_percentage_of_salary`, `termination_policy`.

## قوانین

- `amount` و `installments` الزامی و مثبت.
- قسط دستی: مثبت؛ قسط آخر مثبت؛ `قسط × تعداد + 0.01 >= اصل`.
- سقف مبلغ، تعداد قسط، درصد قسط نسبت به **base_salary جاری**.
- `max_active_loans` در approve چک می‌شود.
- `min_months_between_loans` از `start_date` آخرین وام Active/Completed.
- پرداخت فقط روی وام active و قسط pending.
- تسویه زودهنگام باقیمانده را روی قسط بعدی می‌گذارد و بقیه pending را cancel می‌کند.
- خاتمه: pending → cancelled؛ active → سیاست termination.

## خطاها

| استثنا | کی |
|--------|-----|
| `InvalidArgumentException` | وام خاموش؛ سقف‌ها؛ schedule نامعتبر؛ وضعیت غلط؛ نبود حقوق جاری برای درصد قسط؛ schedule تکراری |
| `InvalidEmployeeLifecycleException` | terminate با policy=`require_settlement` و وام active |

## نتیجه ذخیره‌شده

`hr_loans`:

| فیلد | معنی |
|------|------|
| `loan_number` | خودکار `LN-{year}-{seq}` اگر خالی باشد |
| `amount`, `installments`, `installment_amount` | اصل و قسط منظم |
| `remaining_amount`, `remaining_installments` | مانده |
| `status` | pending / active / completed / cancelled / receivable |
| `start_date`, `end_date` | شروع و آخرین due (یا تاریخ تسویه) |
| `notes` | دلیل رد یا یادداشت خاتمه |

`hr_loan_payments`: `installment_number`, `amount`, `due_date`, `status`, `paid_date`, `payroll_record_id`.

`approve` بعد از commit رویداد `LoanDisbursed` می‌دهد.
