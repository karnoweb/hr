# Implementation Checklist — Phase 8: Payroll

Part of the Karnoweb HR audit. See `HR-AUDIT.md` for the index.

**Sequencing note:** Payroll's calculation engine has a hard dependency on Phase 9's Insurance/Tax calculators — a payroll record cannot be "calculated" without insurance/tax figures. Although Phase 9 is numbered after this phase (per the requested phase ordering, kept here for traceability against the audit's domain list), the actual **build order** should implement Phase 9's `InsuranceCalculator`/`TaxCalculator` (`HR-104`, `HR-106`) before finishing `HR-094`/`HR-095` below. This is the one place this audit deviates from strict numeric phase ordering in practice, and it is called out explicitly per the audit's instructions.

- [ ] HR-089 — Create `PayrollService::openPeriod()` (P0)
  - Area: new `src/Services/PayrollService.php`
  - Acceptance:
    - creates a `PayrollPeriod` for a given branch/year/month, computing `working_days` via Phase 3's `WorkingDayCalculator`
    - respects the existing `unique(branch_id, year, month)` constraint, surfacing a violation as a clear domain exception rather than a raw `QueryException`

- [ ] HR-090 — Implement per-employee aggregation: Attendance → present/absent/late/early-leave (P0)
  - Area: `PayrollService` / new `PayrollCalculator`
  - Acceptance:
    - for a given employee+period, correctly aggregates `AttendanceRecord` rows into `PayrollRecord.working_days`/`present_days`/`absent_days`/`late_minutes`/`early_leave_minutes`

- [ ] HR-091 — Implement per-employee aggregation: Leave → `leave_days_paid`/`leave_days_unpaid` (P0)
  - Area: `PayrollCalculator`
  - Acceptance:
    - correctly sums approved `LeaveRequest` days within the period, split by the leave type's `paid` config flag

- [ ] HR-092 — Implement per-employee aggregation: Overtime → minutes + earnings (P0)
  - Area: `PayrollCalculator`, consuming Phase 5's `OvertimeService::approvedMinutesForPeriod()`
  - Acceptance:
    - correctly aggregates approved overtime minutes by type
    - computes the corresponding earning using `OvertimeType::rate()` against an explicitly documented hourly-equivalent-rate derivation from `base_salary`

- [ ] HR-093 — Implement per-employee aggregation: Salary → `base_salary`, `earnings[]`, `deductions[]`, `gross_earnings`, `gross_salary` (P0)
  - Area: `PayrollCalculator`, consuming Phase 6's `SalaryCalculator`
  - Acceptance:
    - correctly pulls the employee's current salary (via the now-DB-enforced single-current invariant, HR-069) and resolved salary items into `PayrollRecord`'s `earnings`/`deductions` JSON and aggregate totals

- [ ] HR-094 — Integrate Insurance calculation into `PayrollCalculator` (P0)
  - Area: `PayrollCalculator`, consuming Phase 9's `InsuranceCalculator`
  - Acceptance:
    - `insurance_employee`/`insurance_employer`/`insurance_unemployment` correctly populated, respecting the configured ceiling

- [ ] HR-095 — Integrate Tax calculation into `PayrollCalculator` (P0)
  - Area: `PayrollCalculator`, consuming Phase 9's `TaxCalculator`
  - Acceptance:
    - `taxable_income`/`tax` correctly populated, respecting each `SalaryItem.is_taxable` flag and the annual exemption/bracket logic

- [ ] HR-096 — Integrate Loan deduction into `PayrollCalculator` (P0)
  - Area: `PayrollCalculator`, consuming Phase 7's `LoanService::deductionsForPeriod()`
  - Acceptance:
    - `loan_deduction` correctly reflects the sum of due installments for the period
    - the actual `payroll_record_id` linkage on `LoanPayment` and the `Loan` balance decrement happen only at **approval** time (HR-098), not at mere calculation time, so a discarded/recalculated period never double-deducts a loan

- [ ] HR-097 — Implement idempotent `PayrollService::calculate(PayrollPeriod)` orchestration (P0)
  - Area: `PayrollService`
  - Acceptance:
    - uses `updateOrCreate` per `(payroll_period_id, employee_id)` inside a per-employee transaction
    - the period-level status transition (`→ Calculating → Calculated`) is set/cleared transactionally around the whole batch, gated by `PayrollPeriodStatus::canCalculate()` (existing helper, currently unused)
    - a lock on the `PayrollPeriod` row prevents two concurrent "calculate" triggers for the same period
    - re-running `calculate()` on an already-`Calculated` period replaces (not duplicates) each employee's figures
    - concurrency-tested

- [ ] HR-098 — Implement `PayrollService::approve(PayrollPeriod)` gated by `PayrollPeriodStatus::canApprove()` (P0)
  - Area: `PayrollService`
  - Acceptance:
    - wires in the existing, currently-unused `canApprove()` helper
    - sets `approved_at`/`approved_by` via an explicit actor parameter (not `auth()` directly, per the Security findings)
    - only at approval time (not calculation time) commits the loan-deduction linkage (`payroll_record_id`) and decrements `Loan.remaining_amount`/`remaining_installments` (per Phase 7's HR-085 pattern)

- [ ] HR-099 — Implement `PayrollPeriodStatus::canEdit()` enforcement across every payroll mutation path (P0)
  - Area: `PayrollService`, all `PayrollRecord` write paths
  - Acceptance:
    - every mutating method checks `canEdit()`/`canApprove()` first and throws `PayrollPeriodLockedException` (Phase 0, HR-008) otherwise
    - tested that a `Locked`/`Paid`/`Approved` period cannot be recalculated or edited through any exposed method

- [ ] HR-100 — Add a proper enum cast for `PayrollRecord.status` (P1)
  - Area: `src/Models/PayrollRecord.php`
  - Acceptance:
    - consistent with Phase 5's `HR-065` fix for `OvertimeRecord`; reuses `PayrollPeriodStatus` or introduces a dedicated `PayrollRecordStatus` enum, whichever is semantically correct once HR-097–099 are implemented

- [ ] HR-101 — Implement `PayrollService::markPaid(PayrollPeriod)` (P1)
  - Area: `PayrollService`
  - Acceptance:
    - sets `paid_at`, transitions status to `Paid`, gated by the period already being `Approved`
    - this is the point at which Phase 12's accounting event should be dispatched (post-commit)

- [ ] HR-102 — Write the Payroll domain test suite, including a golden-master full-pipeline test (P0)
  - Area: `tests/Feature/`, `tests/Integration/`, `tests/Concurrency/`
  - Acceptance:
    - an Integration test with known attendance/leave/overtime/salary/loan inputs for several employees asserts exact expected `PayrollRecord` outputs
    - idempotent-recalculation test (HR-097), locking-prevents-edits test (HR-099), and concurrent-calculate-trigger test (HR-097) are all included
