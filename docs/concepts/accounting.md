# مرز حسابداری

این پکیج **به هیچ پکیج حسابداری وابسته نیست** و آن را صدا نمی‌زند. فقط بعد از commit، event لاراول می‌فرستد.

بدون نصب حسابداری، HR کامل کار می‌کند.

## Eventها

### `PayrollPeriodApproved`

بعد از `PayrollService::approve()`.

- `$event->period`
- `$event->approvedBy`
- `$event->periodTotals`: `gross_salary`, `net_salary`, `payable`, بیمه، مالیات، `loan_deduction`
- `$event->employees`: به ازای هر کارمند `compensation` / `employee_liabilities` / `employer_liabilities` / `loan_deductions`

### `PayrollPeriodPaid`

بعد از `markPaid()`. همان شکل payload بدون `approvedBy`. یعنی نقد پرداخت شده.

### `LoanDisbursed`

بعد از `LoanService::approve()`. اصل وام و تاریخ شروع.

مصرف‌کننده در اپ میزبان journal می‌زند. گارد معماری: `tests/Architecture/NoAccountingDependencyTest.php`.

`hr.accounting.dispatch_after_commit`: در production بعد از commit؛ در تست‌ها معمولاً `false`.
