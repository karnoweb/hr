# 16. Recommended Execution Order / 17. Blocking Issues / 18. Definition of Done

Part of the Karnoweb HR audit. See `HR-AUDIT.md` for the index and `HR-AUDIT-PHASE-00.md` through `HR-AUDIT-PHASE-14.md` for the full checklist.

## 16. Recommended Execution Order

Follow the 15 phase files in numeric order (`HR-AUDIT-PHASE-00.md` → `HR-AUDIT-PHASE-14.md`), with **one explicit sequencing exception**, called out in `HR-AUDIT-PHASE-08.md`: begin Phase 9's `InsuranceCalculator`/`TaxCalculator` (`HR-104`, `HR-106`) before finishing Phase 8's `HR-094`/`HR-095`, since Payroll's calculation engine cannot be completed without them. All other phase-to-phase dependencies already follow the natural build order (each phase only consumes services/patterns established in an earlier-numbered phase):

1. **Phase 0** — every other phase depends on the sequence generator (`HR-005`), the current-record pattern (`HR-006`), and the exception hierarchy (`HR-008`); do this first, in full, before starting any domain work.
2. **Phase 1 (Employee)** → **Phase 2 (Organization & Contracts)** — Phase 2's `ContractService`/position-assignment fixes reuse Phase 1's lifecycle patterns.
3. **Phase 3 (Attendance & Shifts)** → **Phase 4 (Leave & Mission)** — Phase 4's working-day-aware leave counting depends on Phase 3's `WorkingDayCalculator`.
4. **Phase 5 (Overtime)** — depends on Phase 3's `AttendanceService`.
5. **Phase 6 (Salary)** → **Phase 7 (Loans)** — Phase 7's `max_percentage_of_salary` check depends on Phase 6's `SalaryCalculator`.
6. **Phase 9 (Insurance & Tax)** started early, in parallel with/just ahead of finishing **Phase 8 (Payroll)** — see the sequencing exception above.
7. **Phase 10 (Documents)** → **Phase 11 (Workflow)** — Phase 11's engine replaces logic that lives inside Phase 10's `DocumentService`; fixing Documents' transaction/authorization issues first makes the Workflow engine's job strictly additive rather than a simultaneous rewrite of both.
8. **Phase 12 (Accounting Integration Boundary)** — depends on Phase 8 (events carry payroll data) and Phase 7 (loan events).
9. **Phase 13 (Security/Performance/Hardening)** — a consolidation pass; most individual items can start as soon as their target phase lands, but the phase as a whole is a final sweep.
10. **Phase 14 (Documentation/Release/CI)** — last, since it summarizes and finalizes everything above.

**Suggested delivery granularity:** ship each phase as its own pull request (or small group of PRs within a phase for the largest ones — Phase 8 and Phase 11 are the biggest and may reasonably split into 2-3 PRs each). Do not attempt to land Phases 8 and 11 in one PR — they are independently large and independently risky (miscalculating payroll and mis-routing approvals are both P0-severity failure modes that deserve isolated review).

## 17. Blocking Issues

These **must** be resolved before the package can be called production-ready under any circumstance — each one either corrupts data, crashes at runtime, or allows unauthorized financial/approval actions:

1. **Workflow approver-type resolution crashes for any non-`user` step** (Section 1 & 3; fixed by `HR-125`/`HR-126`/`HR-137`). Blocking because: any workflow using `department_head`, `position`, or `custom` steps — the entire reason those enum values exist — throws an unhandled database exception today.
2. **Employee-code and document-number generation are not concurrency-safe** (fixed by `HR-011`/`HR-113`, both built on `HR-005`). Blocking because: these are the two identifiers most likely to be generated concurrently in any real multi-user HR system (onboarding day, month-end document creation), and today's implementation can either fail the operation outright or (absent the unique constraint catching it, if that constraint were ever relaxed) silently duplicate an identifier.
3. **`EmployeeSalary.is_current`, `EmployeePosition` current/primary, and `Contract` active-status all lack DB-enforced single-current invariants** (fixed by `HR-069`/`HR-030`/`HR-027`). Blocking because: these three facts feed directly into payroll; a silently-duplicated "current" row means payroll can use the wrong salary with no error raised anywhere.
4. **No payroll calculation engine exists at all** (fixed across all of Phase 8, and its Phase 9 dependency). Blocking because: this is the core value proposition of an "HR/payroll core package" — without it, the package cannot be used for its stated purpose.
5. **No authorization check on `approve()`/`reject()`** (fixed by `HR-120`). Blocking because: any caller can approve or reject any document's approval step today, including financially consequential ones (salary changes, loans).
6. **`DocumentService::submit()`/`approve()`/`reject()` are not transactional and are not idempotent under retry** (fixed by `HR-114`/`HR-116`/`HR-117`/`HR-118`). Blocking because: a partial failure today leaves a document permanently stuck in an unrecoverable state (`Pending` with a partial/zero approval set, not re-editable).
7. **Zero automated tests, zero CI** (fixed by Phase 0 + every phase's per-domain test items). Blocking because: none of the fixes above can be trusted to stay fixed without a regression safety net, and a payroll/HR core package with zero tests cannot reasonably be certified production-ready regardless of how correct the code looks on inspection.
8. **Insurance and Tax figures are unversioned, unverified regulatory values with no calculation engine** (fixed by Phase 9, with the explicit `HR-107` legal-verification flag). Blocking because: shipping payroll calculations based on unverified, unversioned legal rates risks real financial/compliance harm — this must be explicitly signed off by someone qualified to verify current Iranian tax/insurance law before v14 is used for real payroll, not merely implemented in code.

## 18. Definition of Done — v14.0.0 / Production HR Core

The package can be tagged **v14.0.0** and described as "production HR core" only when **all** of the following are true:

### Functional completeness
- Every domain audited in `HR-AUDIT-03-DOMAINS.md` has moved from "Schema-only"/"Not started" to at least "Partial" with its P0 checklist items complete; Payroll, Workflow, and Documents specifically must reach a state where their respective headline regression tests (`HR-102`'s golden-master test, `HR-137`'s workflow crash-fix test, `HR-124`'s document-concurrency test) pass.
- Insurance/Tax calculators exist and are wired into Payroll, with every regulatory value explicitly flagged per `HR-107` and reviewed by someone qualified to confirm current legal correctness (this review is **outside the scope of what code/tests can certify** and must happen separately).

### Data integrity
- Every invariant listed in `HR-AUDIT-04-DATABASE-INVARIANTS.md`'s Section 5 table is either "Both" or "Database"-enforced (no invariant remains "Application-level only" or "Missing" for anything marked P0 in that table).
- The branch-scoped uniqueness fixes (`HR-023`) are live, and a multi-branch integration test suite proves two branches can independently use identical catalog codes.

### Concurrency safety
- Every scenario in `HR-AUDIT-06-CONCURRENCY-TRANSACTIONS.md`'s Section 6 table has a passing concurrency test that was failing before its corresponding fix.
- Every transaction boundary in that file's Section 7 table is implemented exactly as specified (no operation flagged "Unsafe" remains unsafe).

### Test requirements
- `HR-171`'s gate is met: every P0 checklist item across all 15 phases has a corresponding automated test that demonstrably fails against the pre-fix code and passes against the post-fix code.
- CI (`HR-002`/`HR-167`) runs the full suite on every PR and is required to pass before merge.
- The standalone-usage Integration test (`HR-143`) passes, proving the package has zero hard dependency on any accounting package.

### Security
- The authorization check from `HR-120` is live and tested (`HR-150`).
- The IDOR/authorization documentation matrix from `HR-150` exists and accounts for every service method added across all phases (either "checked in-package" or "explicitly deferred to caller," with no method left unaccounted for).
- Branch-scoping responsibilities are explicitly documented (`HR-149`) even where not enforced in-package.

### Performance
- No open P0/P1 item remains in `HR-AUDIT-10-ARCHITECTURE-CONFIG-API-PERF-QUALITY.md`'s Section 13 table; the payroll batch-calculation query-count assertion (`HR-155`) passes.

### Documentation
- `README.md` and `docs/USAGE.md` accurately describe only what's actually implemented at release time (`HR-162`/`HR-163`), with any remaining roadmap items clearly labeled as such.
- `docs/ACCOUNTING.md` (`HR-142`) and `docs/ARCHITECTURE.md` (`HR-170`) exist.
- `CHANGELOG.md`'s "Unreleased" section (`HR-010`/`HR-165`) has been fully populated and is ready to become the v14.0.0 release notes (`HR-168`).

### CI / release quality
- Pint and Larastan both run in CI with committed baselines (`HR-003`/`HR-004`/`HR-151`–`153`), and the codebase passes both without relying on an ever-growing baseline exception list for anything touched during Phases 1–13.
- `LICENSE` file exists (`HR-009`).
- The final repository review (`HR-172`) confirms every change traces back to a specific checklist item, and no source file was modified outside the scope any given checklist item explicitly authorized.

**Until every box above is checked, the package should continue to be described — accurately, and without discouragement, since the domain design underneath it is genuinely sound — as an HR data model with a growing service layer, not yet a production HR/payroll core.**
