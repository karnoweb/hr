# Implementation Checklist — Phase 12: Accounting Integration Boundary

Part of the Karnoweb HR audit. See `HR-AUDIT.md` for the index. Depends on Phase 8 (Payroll) and Phase 7 (Loans). **No accounting logic is implemented in this package** — this phase only defines the boundary.

- [ ] HR-139 — Define a `PayrollPeriodApproved` event carrying period totals and per-employee breakdowns (P1)
  - Area: new `src/Events/PayrollPeriodApproved.php`
  - Acceptance:
    - payload includes period id and, per employee, gross/net/`insurance_employee`/`insurance_employer`/`tax`/`loan_deduction`/`payable`
    - dispatched from `PayrollService::approve()` (Phase 8, HR-098) **after** the transaction commits (e.g. via `DB::afterCommit()`), never inside it
    - unit-tested that the event fires exactly once per approval and carries correct data

- [ ] HR-140 — Define a `LoanDisbursed` event (P2)
  - Area: new `src/Events/LoanDisbursed.php`
  - Acceptance:
    - dispatched from `LoanService`'s creation path (Phase 7, HR-084) after commit
    - carries loan id, employee id, amount, date

- [ ] HR-141 — Define a `PayrollPeriodPaid` event (P2)
  - Area: new `src/Events/PayrollPeriodPaid.php`
  - Acceptance:
    - dispatched from `PayrollService::markPaid()` (Phase 8, HR-101) after commit

- [ ] HR-142 — Document the accounting integration contract explicitly (P1)
  - Area: new `docs/ACCOUNTING.md` (or a section in `docs/USAGE.md`)
  - Acceptance:
    - states plainly: "this package never depends on or calls an accounting package directly; it only fires events that an accounting integration may listen to"
    - lists every event from HR-139–141 with its full payload shape

- [ ] HR-143 — Add an Integration test proving the package works fully with zero accounting package installed (P0)
  - Area: new `tests/Integration/StandaloneUsageTest.php`
  - Acceptance:
    - employee creation, leave request, document submit/approve, and (once Phase 8 lands) payroll calculation all work correctly with no accounting package present in the test environment
    - this test is the single concrete proof that no dangerous coupling has crept in; add it as soon as Phase 8/11's engines exist and keep it green permanently afterward

- [ ] HR-144 — Verify `PayrollRecord`'s existing schema is sufficient for accounting consumers without modification (P2)
  - Area: review only — no code change expected unless a genuine gap is found
  - Acceptance:
    - confirmed via HR-139's event design that no additional `PayrollRecord` columns are needed for a typical accounting integration
    - if a gap is found, a new, narrowly-scoped column is proposed with a concrete consumer justification (not speculative)

- [ ] HR-145 — Add a lightweight guard preventing future direct dependencies on an accounting package's classes from `src/Services`/`src/Calculators` (P2)
  - Area: CI check or a documented review-checklist item
  - Acceptance:
    - a low-maintenance guard exists — a documented review-checklist item is acceptable if a tooling rule (e.g. a custom PHPStan/architecture rule) would be disproportionate to the actual risk, per the "do not over-engineer" instruction

- [ ] HR-146 — Represent employer insurance contribution and salary-payable as clearly distinct liability-shaped figures in the event payload (P1)
  - Area: HR-139's payload design
  - Acceptance:
    - the payload structure makes it unambiguous to an accounting consumer which figures are expenses vs. liabilities vs. the net payable amount

- [ ] HR-147 — Include loan-deduction detail sufficient for accounting to reduce the corresponding receivable (P1)
  - Area: HR-139's payload design, depends on Phase 8 HR-096/098
  - Acceptance:
    - the per-employee breakdown in `PayrollPeriodApproved` includes loan id and installment amount deducted, sufficient for an accounting consumer to correctly reduce the loan receivable

- [ ] HR-148 — Write the Accounting Integration boundary test suite (P1)
  - Area: `tests/Integration/`, `tests/Unit/`
  - Acceptance:
    - covers HR-139–143; the standalone-usage test (HR-143) is the headline deliverable of this phase
