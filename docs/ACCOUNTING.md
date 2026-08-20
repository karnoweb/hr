# Accounting integration boundary

This package **never depends on or calls an accounting package directly**. It only fires Laravel events that an accounting integration (for example `karnoweb/laravel-accounting`) may listen to in the host application.

HR remains fully functional with **zero accounting package installed**.

## Events

### `Karnoweb\Hr\Events\PayrollPeriodApproved`

Dispatched from `PayrollService::approve()` **after** the DB transaction commits.

**When:** loan installments are committed to `LoanPayment` and the period becomes `approved`.

**Payload:**

```php
$event->period;          // PayrollPeriod model
$event->approvedBy;      // int|null user id
$event->periodTotals;    // summed figures (see below)
$event->employees;       // per-employee breakdown (see below)
```

**`periodTotals` keys:** `gross_salary`, `net_salary`, `payable`, `insurance_employee`, `insurance_employer`, `insurance_unemployment`, `tax`, `loan_deduction`

**Each employee entry:**

| Section | Keys | Accounting role |
|---------|------|-----------------|
| `compensation` | `gross_salary`, `net_salary`, `payable` | Expense / net cash payable |
| `employee_liabilities` | `insurance_employee`, `tax`, `loan_deduction` | Withholdings payable |
| `employer_liabilities` | `insurance_employer`, `insurance_unemployment` | Employer contribution liabilities |
| `loan_deductions` | `[{loan_id, loan_payment_id, amount, installment_number}]` | Reduce loan receivable |

### `Karnoweb\Hr\Events\PayrollPeriodPaid`

Dispatched from `PayrollService::markPaid()` after commit.

Same payload shape as `PayrollPeriodApproved` (without `approvedBy`). Signals that cash was disbursed to employees.

### `Karnoweb\Hr\Events\LoanDisbursed`

Dispatched from `LoanService::approve()` after commit.

```php
$event->loan;           // Loan model
$event->employeeId;     // int
$event->amount;         // float principal
$event->disbursedAt;   // Y-m-d (loan start_date)
```

## Host application example

```php
use Karnoweb\Hr\Events\PayrollPeriodApproved;

Event::listen(PayrollPeriodApproved::class, function (PayrollPeriodApproved $event) {
    // Post journal entries in your accounting package — outside karnoweb/hr.
});
```

## Schema note (HR-144)

`PayrollRecord` already exposes `gross_salary`, `net_salary`, `payable`, insurance/tax columns, and `calculation_log.loan_payments` for installment detail. No additional HR columns are required for a typical accounting consumer.

## Coupling guard (HR-145)

Code review + CI: `src/Services/` and `src/Calculators/` must not import accounting package classes (`Karnoweb\\Accounting`, `laravel-accounting`, etc.). Enforced by `tests/Architecture/NoAccountingDependencyTest.php`.
