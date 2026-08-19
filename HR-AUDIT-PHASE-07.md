# Implementation Checklist — Phase 7: Loans

Part of the Karnoweb HR audit. See `HR-AUDIT.md` for the index. Depends on Phase 6 (SalaryCalculator, for `max_percentage_of_salary`).

- [ ] HR-079 — Add a unique constraint on `loan_payments(loan_id, installment_number)` (P1)
  - Area: new migration
  - Acceptance:
    - duplicate installment rows for the same loan are rejected at the database level

- [ ] HR-080 — Create `LoanService::apply()`/`approve()` enforcing `max_amount`, `max_installments`, `min_installments` (P0)
  - Area: new `src/Services/LoanService.php`
  - Acceptance:
    - requests exceeding any configured limit are rejected with a clear exception
    - limits are read from `config('hr.loan.*')` at request time, not cached stale

- [ ] HR-081 — Enforce `max_active_loans` with a locked, transactional eligibility check (P0)
  - Area: `LoanService`
  - Acceptance:
    - `lockForUpdate()` on the employee's active-loan set for the duration of the check-and-insert
    - a concurrency test with two simultaneous applications exactly at the `max_active_loans` boundary proves only the allowed number succeed

- [ ] HR-082 — Enforce `min_months_between_loans` (P1)
  - Area: `LoanService`
  - Acceptance:
    - a new loan application within the configured cooldown of the employee's most recent loan is rejected
    - the reference point (most recent loan's `start_date` vs. completion date) is explicitly documented and tested

- [ ] HR-083 — Enforce `max_percentage_of_salary` against the employee's current salary (P0)
  - Area: `LoanService`, consuming Phase 6's `SalaryCalculator`
  - Acceptance:
    - `installment_amount` relative to the employee's current salary base (base vs. gross — decision documented) cannot exceed the configured percentage
    - rejected with a clear exception otherwise

- [ ] HR-084 — Generate the `LoanPayment` installment schedule atomically on loan creation (P0)
  - Area: `LoanService`
  - Acceptance:
    - creating a `Loan` and its N `LoanPayment` rows happens inside one transaction
    - `due_date` spacing is derived consistently (e.g. monthly from `start_date`)
    - a simulated partial failure leaves no orphaned `Loan` without its full schedule

- [ ] HR-085 — Implement `LoanService::recordPayment()` keeping `Loan.remaining_amount`/`remaining_installments` in sync with `LoanPayment` atomically (P0)
  - Area: `LoanService`
  - Acceptance:
    - marking a `LoanPayment` paid and decrementing the parent `Loan`'s remaining fields happens in one transaction
    - a test proves these two never drift out of sync even under a simulated partial failure

- [ ] HR-086 — Implement early repayment (settle `remaining_amount` in one operation) (P1)
  - Area: `LoanService`
  - Acceptance:
    - early repayment atomically settles the loan, sets `status = Completed`, and cancels (not deletes) future-dated `LoanPayment` rows for audit-trail purposes

- [ ] HR-087 — Expose `LoanService::deductionsForPeriod()` for Phase 8's payroll calculator (P1)
  - Area: `LoanService`
  - Acceptance:
    - `deductionsForPeriod(Employee $employee, PayrollPeriod $period)` returns due installments for that period, ready for Phase 8 to consume and later link back via `payroll_record_id`

- [ ] HR-088 — Write the Loans domain test suite (P0)
  - Area: `tests/Feature/`, `tests/Concurrency/`
  - Acceptance:
    - covers every limit in HR-080–083 (happy path + rejection), schedule generation (HR-084), repayment consistency (HR-085/086), and the active-loan-count concurrency scenario (HR-081)
