# Karnoweb HR — Full Domain Audit (Index)

> This audit is split across multiple files (one per major section, and **one file per implementation-checklist phase**) to avoid truncation. This file is the entry point / table of contents. No source code was modified while producing this audit.

**Audit date:** 2026-08-19
**Repository:** `karnoweb/hr` (local working copy at `d:\my-packages\hr`)
**Method:** Direct inspection of every tracked file (`git ls-files`) — all 27 migrations, all ~30 models, all 14 enums, all 3 services, the service provider, facade, config, docs, and the original design blueprint (`hr-package.md`). No assumptions were made from schema/README alone; every claim below is backed by a concrete file reference.

## Document Map

| # | File | Contents |
|---|------|----------|
| 1–2 | `HR-AUDIT.md` (this file) | Executive Summary, Previous Audit Verification |
| 3 | `HR-AUDIT-03-DOMAINS.md` | Domain-by-domain audit (Organization → Accounting Integration) |
| 4–5 | `HR-AUDIT-04-DATABASE-INVARIANTS.md` | Database/Data-model audit, Domain Invariants |
| 6–7 | `HR-AUDIT-06-CONCURRENCY-TRANSACTIONS.md` | Concurrency & Idempotency, Transaction Boundaries |
| 8–9 | `HR-AUDIT-08-SECURITY-TESTING.md` | Security & Authorization, Testing Gap Analysis |
| 10–14 | `HR-AUDIT-10-ARCHITECTURE-CONFIG-API-PERF-QUALITY.md` | Architecture Recommendations, Configuration Audit, Public API Audit, Performance Audit, Package/Release Quality |
| 15 | `HR-AUDIT-PHASE-00.md` … `HR-AUDIT-PHASE-14.md` (15 files) | Implementation Checklist, one file per phase |
| 16–18 | `HR-AUDIT-FINAL-SUMMARY.md` | Recommended Execution Order, Blocking Issues, Definition of Done |

---

## 1. Executive Summary

### Current maturity score: **3.5 / 10** (as a *domain/business-logic* package)
### Schema/data-model maturity: **7.5 / 10**
### Production readiness score: **2.5 / 10**

These scores are lower than the preliminary audit's informal 5.5/10. The preliminary audit was directionally correct on every point it raised (see Section 2 — all 11 claims are **CONFIRMED**), but a full pass over every migration, model, enum and service shows the gap between schema and behavior is **wider** than the preliminary pass suggested, and several of the "incomplete" areas are actually **broken at runtime**, not just incomplete.

### Biggest risks (P0)

1. **Workflow engine will throw a database error for any non-`user` approver type.** `document_approvals.assigned_to` is a `NOT NULL` column (`database/migrations/2024_01_01_000019_create_hr_document_approvals_table.php:17`), but `DocumentService::submit()` copies `$step->approver_id` verbatim into `assigned_to` (`src/Services/DocumentService.php:80`) without ever resolving `WorkflowStep::approver_type` (`department_head`, `position`, `custom`). For any step where `approver_id` is null (the normal case for those types), the `INSERT` will violate the NOT NULL constraint and the whole `submit()` call fails with an unhandled `QueryException`. This is not a "missing feature", it is a **crash bug** in the only workflow code path that exists.
2. **No calculation engine exists for Payroll, Insurance, Tax, Overtime, or Attendance**, despite full schema for all of them. `PayrollRecord` has 20+ numeric columns (insurance, tax, net salary, payable, …) that are simply schema — nothing in `src/` ever computes them.
3. **Three separate, unenforced "exactly one current record" invariants** (`EmployeeSalary.is_current`, `EmployeePosition` primary/current, `Contract` active) can each be violated by ordinary concurrent writes, silently corrupting the single most financially sensitive fact in the system: "what is this employee's current salary/position/contract".
4. **Two independent, provably racy sequence-generation routines** (`Employee::generateEmployeeCode()` and `HrDocument::generateDocumentNumber()`) generate business-critical unique identifiers without real atomicity.
5. **No authorization checks anywhere.** `DocumentService::approve()`/`reject()` accept any `DocumentApproval` model instance and never verify `auth()->id() === $approval->assigned_to`. Any authenticated (or unauthenticated, if called from a job/CLI) caller who can obtain/guess an approval ID can approve or reject someone else's document.
6. **Zero automated tests, zero CI**, despite `phpunit/phpunit` and `orchestra/testbench` being installed in `vendor/` (present in `composer.lock`'s `packages-dev`) — the test tooling was set up and then never used (no `tests/` directory, no `phpunit.xml`, no `.github/`).

### Biggest strengths

- The **domain vocabulary and schema** are genuinely well thought out: 27 migrations covering Organization → Payroll → Workflow with sensible normalization (structures/items pattern for salary, period/record pattern for payroll, step/approval pattern for workflow).
- Consistent use of **backed enums** with `label()`/`color()` and bilingual (fa/en) translation files — a real, working piece of infrastructure that many packages skip.
- **Polymorphic `employable`** relationship correctly decouples `Employee` from any specific `User` model, and `branch_id` is deliberately left as a loose, unconstrained column so the package doesn't hard-depend on a `Branch` model — appropriate for a package meant to be reused across ERP products.
- Two DB-level invariants that **are** correctly enforced: `attendance_records` has `unique(['employee_id','date'])` and `payroll_records` has `unique(['payroll_period_id','employee_id'])` — both prevent real, plausible duplicate-record bugs.
- The package correctly avoids over-engineering in the parts that *are* built: no needless repository layer, no premature DTOs.

### Overall conclusion

The package is best described today as a **well-designed HR data model with three thin CRUD services**, not an HR domain/core package. The original design blueprint (`hr-package.md`) itself stops at the model layer — it lists `Calculators/`, `Events/`, `Rules/`, `Contracts/` in its intended folder structure (line 24-29) but never actually specifies any of them in detail. So the implementation gap is not "the plan wasn't followed" — **the plan itself was never finished past the schema.** This does not mean the package should be rewritten: the domain boundaries are correct and worth keeping. It means ~10 domains (Attendance, Shift, Overtime, Salary, Payroll, Insurance, Tax, Loan, Workflow-engine, Contract-lifecycle) need a service/calculator layer built from scratch, and 3 concrete concurrency/invariant bugs need fixing before *any* of this can be trusted with real money or real approval chains.

---

## 2. Previous Audit Verification

Legend: `[C]` CONFIRMED · `[P]` PARTIALLY CONFIRMED · `[N]` NOT REPRODUCIBLE · `[F]` FALSE/OUTDATED · `[?]` NEEDS FURTHER INVESTIGATION

| # | Finding (from preliminary audit) | Status | Evidence | Severity |
|---|---|---|---|---|
| 1 | Business logic is very incomplete; only `EmployeeService`, `LeaveService`, `DocumentService` exist | **[C]** CONFIRMED — and understated. Verified `src/Services/` contains exactly 3 files. There is **no** service/calculator for Attendance, Shift, Overtime, Salary, Payroll, Insurance, Tax, Loan, or Contract lifecycle at all — 9+ domains, not "a few gaps". | `src/Services/*.php` (dir listing: `DocumentService.php`, `EmployeeService.php`, `LeaveService.php` only) | P0 |
| 2 | Document-number generation has a concurrency bug (`lockForUpdate()->count()+1` inside a transaction that ends before the real INSERT) | **[C]** CONFIRMED exactly as described. The `lockForUpdate()` executes and releases inside `DB::transaction()` in the `creating` event handler; the actual model `INSERT` happens afterward, outside that transaction, so the lock provides no protection against a concurrent request computing the same sequence number. | `src/Models/HrDocument.php:98-110` (`generateDocumentNumber()`), invoked from `booted()` at line 91-95 | P0 |
| 3 | Employee-code generation has a race condition (`orderByDesc('employee_code')->value(...)` then `+1`, no locking) | **[C]** CONFIRMED exactly as described. No `lockForUpdate()`, no transaction, no sequence table — pure read-then-compute-then-insert. | `src/Services/EmployeeService.php:109-145` (`generateEmployeeCode()`) | P0 |
| 4 | Workflow is incomplete: `condition` not evaluated, `timeout_action` not executed, `approver_type` not resolved, required/optional not respected, no explicit sequential/parallel semantics, no escalation | **[C]** CONFIRMED, and **worse than described**: because `approver_type` is never resolved, `assigned_to` is set to `$step->approver_id` unconditionally (`src/Services/DocumentService.php:80`); since `document_approvals.assigned_to` is `NOT NULL` (`database/migrations/...000019...php:17`) and `approver_id` is nullable and normally null for `department_head`/`position`/`custom` steps, this is a **guaranteed runtime SQL error**, not merely a "feature gap". Also confirmed: all steps for a workflow are inserted as `pending` in the same loop (`src/Services/DocumentService.php:76-84`) regardless of `order`, so there is no real sequential gating — every step is effectively parallel today. | `src/Services/DocumentService.php:65-88`, `src/Models/WorkflowStep.php`, `database/migrations/2024_01_01_000018...php`, `...000019...php:17` | P0 |
| 5 | Logic bug: if no workflow is found on submit, the document becomes `Pending` forever with no approvals and `skip_on_no_approver=false` is not honored | **[C]** CONFIRMED. `submit()` unconditionally sets status to `Pending` (`src/Services/DocumentService.php:71`) before checking `if ($workflow)` (line 75); there is no `else` branch, so when `Workflow::findForDocument()` returns `null`, the method returns with the document permanently stuck in `Pending` and zero `DocumentApproval` rows — nothing can ever call `advanceDocumentStatusIfComplete()` for it. `config('hr.workflow.skip_on_no_approver')` (`config/hr.php:256`) is defined but never read anywhere in the codebase (confirmed via repo-wide search — only 8 `config(...)` call sites exist, none reference `skip_on_no_approver` or `auto_approve_own_department`). | `src/Services/DocumentService.php:65-88`; config key defined but unused: `config/hr.php:255-257` | P0 |
| 6 | `LeaveService` is basically CRUD; none of the config-declared business rules (balance, overlap, holidays, working days, carry-over, hourly cap, once-per-employment, etc.) are enforced | **[C]** CONFIRMED exactly. `LeaveService` has exactly two methods, `request()` and `balance()`; `request()` does nothing but cast two dates and default `status`, then `LeaveRequest::create($data)` — no read of `LeaveBalance`, no read of `config('hr.leave.types')`, no overlap query, no holiday/working-day math. | `src/Services/LeaveService.php:28-44` (entire class body) | P0 |
| 7 | Payroll is schema-only; no calculation engine | **[C]** CONFIRMED. Confirmed by absence: no `PayrollService`, no `Calculators/` directory, no code anywhere that reads `PayrollRecord`'s 20 numeric columns and computes them from attendance/leave/overtime/salary/insurance/tax/loans. | Absence confirmed across `src/` (no matching files/classes) | P0 |
| 8 | `EmployeeSalary.is_current` has no DB-level uniqueness — only a plain index, so concurrent writes can create multiple "current" salaries per employee | **[C]** CONFIRMED exactly. Migration defines `$table->index(['employee_id', 'is_current'])` (non-unique) with no supplementary unique constraint of any kind. | `database/migrations/2024_01_01_000022_create_hr_employee_salaries_table.php:24` | P0 |
| 9 | `Hr` facade instantiates services with `new EmployeeService` etc. instead of resolving through the container | **[C]** CONFIRMED. `Hr::employees()`/`leave()`/`documents()` each lazily `new` the service and cache it on the `Hr` singleton instance itself (not in the container), so DI, mocking in tests, and future constructor dependencies (e.g. injecting a `TaxCalculator` into a future `PayrollService`) are all harder than they need to be. | `src/Hr.php:22-47` | P1 |
| 10 | No real automated tests exist despite `phpunit`/`testbench` being declared dev dependencies; no CI | **[C]** CONFIRMED. `composer.json` declares the dev deps and an `autoload-dev` PSR-4 mapping to `tests/` (`composer.json:19-21,28-32`), and `composer.lock`'s `packages-dev` section confirms `orchestra/testbench` and `phpunit/phpunit` are **actually installed** in `vendor/` — but there is no `tests/` directory, no `phpunit.xml`/`phpunit.xml.dist`, no Pest config, and no `.github/` directory anywhere in the repo (verified via full directory listing and glob search). The test *infrastructure* was provisioned and then abandoned before a single test was written. | `composer.json:19-32`; `composer.lock` `packages-dev` section (lines ~6320-6340 install `orchestra/testbench-core`, `phpunit/phpunit`); confirmed absent: `tests/`, `.github/`, `phpunit.xml*` | P0 |
| 11 | README/docs describe the package (payroll, workflow, attendance, etc.) as if functionally complete, but only the schema/model layer backs most of these claims | **[C]** CONFIRMED. `README.md` markets "payroll, leave, attendance, workflow, and document management" (`README.md:3`) and `docs/USAGE.md` shows example code for `Shift`, `AttendanceRecord`, `OvertimeRecord`, `EmployeeSalary`, `Loan` that is **pure `Model::create([...])` CRUD with no validation** — e.g. `docs/USAGE.md:493` explicitly tells the integrator to "do the real payroll calculation yourself in your service/calculator layer", which is an honest admission the package doesn't do it, but this caveat is buried mid-document and absent from `README.md`'s top-level pitch. | `README.md:3`; `docs/USAGE.md:362-519` | P1 |

**Verification conclusion:** all 11 claims from the preliminary audit are independently reproduced against the actual source. None were false or outdated. Several (4, 5, 8, 10) are demonstrably **more severe** than the original phrasing suggested. See `HR-AUDIT-03-DOMAINS.md` onward for issues **not** raised in the preliminary audit at all (multi-branch uniqueness conflicts, missing overlap protection for leave/mission, inconsistent enum casting, IDOR in approvals, soft-delete/FK interaction in `Department`, and more).
