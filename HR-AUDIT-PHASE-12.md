# Implementation Checklist — Phase 12: Accounting Integration Boundary

Part of the Karnoweb HR audit. See `HR-AUDIT.md` for the index. Depends on Phase 8 (Payroll) and Phase 7 (Loans). **No accounting logic is implemented in this package** — this phase only defines the boundary.

- [x] HR-139 — Define a `PayrollPeriodApproved` event carrying period totals and per-employee breakdowns (P1)
- [x] HR-140 — Define a `LoanDisbursed` event (P2)
- [x] HR-141 — Define a `PayrollPeriodPaid` event (P2)
- [x] HR-142 — Document the accounting integration contract explicitly (P1)
  - `docs/ACCOUNTING.md`
- [x] HR-143 — Add an Integration test proving the package works fully with zero accounting package installed (P0)
  - `tests/Integration/StandaloneUsageTest.php`
- [x] HR-144 — Verify `PayrollRecord`'s existing schema is sufficient for accounting consumers without modification (P2)
  - Documented in `docs/ACCOUNTING.md`; no schema change required.
- [x] HR-145 — Add a lightweight guard preventing future direct dependencies on an accounting package's classes (P2)
  - `tests/Architecture/NoAccountingDependencyTest.php`
- [x] HR-146 — Represent employer insurance contribution and salary-payable as clearly distinct liability-shaped figures in the event payload (P1)
- [x] HR-147 — Include loan-deduction detail sufficient for accounting to reduce the corresponding receivable (P1)
- [x] HR-148 — Write the Accounting Integration boundary test suite (P1)
  - `tests/Feature/AccountingBoundaryTest.php`
