# Changelog

## [Unreleased]

## [13.1.0] - 2026-08-20

Audit-complete release (Phases 0–14) on the **Laravel 13 package line** (`karnoweb/hr` v13.x). Substantial domain implementation vs. early v13.0 schema-only state.

### Added

- Full domain services: employees, contracts, attendance, shifts, leave, missions, overtime, salary, loans, payroll, documents, workflow (Phases 1–11).
- Insurance/tax calculators, rate import, employee exemptions (Phase 9).
- Accounting boundary events (`PayrollPeriodApproved`, `PayrollPeriodPaid`, `LoanDisbursed`) without accounting package dependency (Phase 12).
- Security/performance hardening: branch-scoping docs, authorization matrix tests, payroll batch queries (Phase 13).
- [CONTRIBUTING.md](CONTRIBUTING.md), expanded [README.md](README.md) and [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).

### Changed

- **Breaking vs. early v13.0.x:** use service methods (`Hr::…()`) instead of raw Eloquent for governed entities; document approval requires explicit `actorId` outside web auth; workflow configuration validated on save.

### Notes

- Package major version stays **13** to match Laravel 13 — not a separate semver major.
- Host app must implement branch scoping, authorization policies, and rate limiting — see [docs/USAGE.md](docs/USAGE.md).
- Insurance/tax rate seeds marked **NEEDS VERIFICATION** for production payroll.
- CI: PHPUnit + Pint + PHPStan on every PR (`.github/workflows/tests.yml`).

## [13.0.6] - 2026-08-20

Phase 13 of the HR audit: security documentation and payroll query batching.

### Added

- Multi-branch integration checklist and rate-limit guidance in `docs/USAGE.md` (HR-149, HR-158).
- `PayrollBatchContext` for batched payroll-period calculation queries (HR-155).
- `tests/Security/ServiceAuthorizationMatrixTest.php` and `tests/Performance/PayrollBatchQueryTest.php` (HR-150, HR-160).
- `ConditionEvaluator::validateConditions()` with fail-closed validation on `Workflow`/`WorkflowStep` save (HR-157).

## [13.0.5] - 2026-08-20

Phases 11–12 of the HR audit: workflow engine and accounting integration boundary.

### Added

- `WorkflowEngine`, `ApproverResolver`, `ConditionEvaluator`, and `WorkflowValidator` with sequential/parallel execution, step conditions, timeouts, and escalation (HR-125–HR-138).
- `hr:process-workflow-timeouts` command; `DocumentStatus::Cancelled` and `DocumentService::cancel()`.
- Migrations: `departments.head_employee_id`, `workflows.execution_mode`, `workflow_steps.escalation_user_id`.
- Phase 12 Accounting boundary: `PayrollPeriodApproved`, `PayrollPeriodPaid`, and `LoanDisbursed` events via `AccountingEventDispatcher` (post-commit in production), `docs/ACCOUNTING.md`, standalone usage test, and architecture guard.

### Changed

- `DocumentService` integrated with workflow engine for submit/approve/reject/cancel lifecycle.
- `PayrollService` and `LoanService` dispatch accounting events after commit (configurable via `hr.accounting.dispatch_after_commit`).

### Notes

- Tests set `hr.accounting.dispatch_after_commit` to `false` so events fire under `RefreshDatabase`.
- Workflow approver resolution requires `departments.head_employee_id` or explicit user/role configuration.

## [13.0.4] - 2026-08-19

Phases 9–10 of the HR audit: insurance/tax completion and documents hardening.

### Added

- `hr:import-rates` command and `RatesImportService` for importing versioned insurance/tax rows from JSON (HR-111).
- Per-employee exemption fields on `Employee`: `insurance_exempt`, `tax_exempt`, `additional_tax_exemption` (HR-109).
- Config-gated `dependents_count` tax exemption via `hr.tax.dependents_exemption` (HR-110).
- Concurrency-safe document numbers via `SequenceGenerator` (HR-113).
- Transactional `DocumentService::submit()`/`approve()`/`reject()` with document row locking (HR-114–HR-118).
- Explicit `actorId` on document lifecycle methods; `UnauthorizedApprovalException` when actor ≠ `assigned_to` (HR-119–HR-120).
- Branch validation on `DocumentService::create()` and `resubmit()` for rejected documents (HR-121–HR-122).
- Document domain Feature tests (HR-124).

### Notes

- `approve()`/`reject()` require a matching `actorId` when not running in an authenticated web context.
- Dependents tax exemption remains disabled by default (**NEEDS VERIFICATION**).

## [13.0.3] - 2026-08-19

Phases 7–8 of the HR audit (loans + payroll), with insurance/tax calculators from Phase 9.

### Added

- `LoanService` with apply/approve/reject, config-driven limits, installment schedule generation, `recordPayment`, `settleEarly`, and `deductionsForPeriod()` (HR-080–HR-087).
- `LoanPaymentStatus` enum cast on `LoanPayment`.
- Unique constraint on `loan_payments(loan_id, installment_number)` (HR-079).
- `Hr::loans()` facade accessor.
- Loan domain Feature tests (HR-088).
- `PayrollService` with openPeriod/calculate/approve/markPaid lifecycle (HR-089 / HR-097–HR-101).
- `PayrollCalculator` aggregating attendance, leave, overtime, salary, loans (HR-090–HR-096).
- `InsuranceCalculator` and `TaxCalculator` with versioned `insurance_rates` / `tax_brackets` tables (HR-103–HR-106).
- `PayrollRecordStatus` enum cast; loan deductions committed only on period approve (HR-098).
- `Hr::payroll()` facade accessor.
- Payroll + insurance/tax tests including golden-master pipeline (HR-102 / HR-112).

### Notes

- Use `Hr::loans()` for loan lifecycle; do not create active loans without installment schedules.
- Loan payroll deductions are applied on `PayrollService::approve()`, not during `calculate()`.
- Insurance/tax rate values are seeded with **NEEDS VERIFICATION** — verify before production payroll use.

## [13.0.2] - 2026-08-19

Phases 5–6 of the HR audit implementation: overtime and salary domains.

### Added

- `OvertimeService` with attendance sync, approve/reject lifecycle, night/holiday classification (`OvertimeMinuteClassifier`), `min_minutes`, `monthly_cap`, and `requires_pre_approval` gating (HR-061–HR-067).
- `OvertimeStatus` enum cast on `OvertimeRecord` (HR-065).
- `Hr::overtime()` facade accessor.
- Overtime unit/feature tests including `OvertimeType::rate()` (HR-068).
- `SalaryService` with assign/changeSalary lifecycle and `current_key` DB invariant (HR-069 / HR-070).
- `SalaryCalculator` for Fixed/Percentage/Formula items with taxable/insurable totals (HR-071–HR-073 / HR-076).
- Safe `SalaryExpressionEvaluator` (no eval) and `SalaryItemValidator` for percentage_of/formula rules (HR-072 / HR-073).
- `Hr::salaries()` facade accessor (HR-075).
- Salary domain tests including historical preservation (HR-074 / HR-078).

### Changed

- `AttendanceService::clockOut()` now computes overtime buckets and syncs `OvertimeRecord` rows by default.
- `EmployeeService::terminate()` clears `employee_salaries.current_key` when closing current salary.

### Notes

- Do not create current salaries via `EmployeeSalary::create()` without managing `current_key`; use `Hr::salaries()->assign()` / `changeSalary()`.
- `config('hr.overtime.monthly_cap')` is in **minutes**; approving overtime that would exceed the cap is rejected (not silently truncated).

## [13.0.1] - 2026-08-19

Phases 0–4 of the HR audit implementation: foundation, employee domain, organization/contracts, attendance/shifts, and leave/mission.

### Added

- Test harness (Orchestra Testbench + PHPUnit) with `phpunit.xml` and package bootstrap tests (HR-001).
- GitHub Actions CI workflow for PHP 8.3 / Laravel 13 (tests, Pint, PHPStan) (HR-002 / HR-003 / HR-004).
- Laravel Pint (`pint.json`) and Larastan/PHPStan (`phpstan.neon`) as direct dev dependencies (HR-003 / HR-004).
- `hr_sequences` table and `Karnoweb\Hr\Support\SequenceGenerator` for atomic sequence allocation (HR-005).
- `docs/ARCHITECTURE.md` documenting the reusable "exactly one current record" (`current_key`) DB pattern (HR-006).
- Domain exception hierarchy rooted at `HrException` (HR-008).
- MIT `LICENSE` file (HR-009).
- Atomic employee-code generation via `SequenceGenerator` (HR-011).
- Unique indexes on `employees(employable_type, employable_id)` and `employees.national_id` (HR-013 / HR-014).
- `EmployeeService::terminate()` / `reactivate()` / `suspend()` lifecycle methods (HR-015 / HR-016 / HR-017).
- Opt-in `IranianNationalId` checksum helper / validation rule (HR-020).
- `InvalidEmployeeLifecycleException` for invalid status/date transitions (HR-018 / HR-022).
- Employee domain Feature/Unit tests (HR-021).
- Branch-scoped catalog `code` uniqueness migrations for departments, positions, shifts, shift patterns, salary items, salary structures (HR-023).
- `ContractService` with `hire()`, `renew()`, `extend()`, `terminate()` and `Hr::contracts()` facade entry (HR-028).
- `active_key` / `current_key` DB invariants for contracts and primary employee positions (HR-027 / HR-030).
- `InvalidOrganizationStructureException` for department tree violations (HR-024 / HR-025).
- `HrDocumentReference` app-level validation for optional `hr_document_id` at contract and position write sites (HR-033).
- Organization + Contract Feature tests (HR-034).
- `AttendanceService` with `clockIn()` / `clockOut()` / `ensureDayRecord()` / `adjust()` and `Hr::attendance()` (HR-035 / HR-040 / HR-042).
- `ShiftResolver` and `ShiftAssignmentService` (`Hr::shiftAssignments()`) with rotating pattern support (HR-036 / HR-039).
- `WorkingDayCalculator` shared helper for working-day counts (HR-041).
- `hr:auto-clock-out` Artisan command registered on the scheduler (HR-038).
- Shift assignment `current_key` DB invariant migration (HR-039).
- `ShiftPattern` JSON structure validation (HR-044).
- Attendance + Shift Feature/Unit tests (HR-046).
- `LeaveBalanceService`, leave balance validation, overlap detection, and `approve()`/`reject()`/`cancel()` lifecycle (HR-047–HR-054).
- `MissionService` with leave/mission overlap checks and optional `HrDocument` creation (HR-056–HR-057).
- Attendance auto-marking for approved leave/mission working days (HR-058).
- `hr:carry-over-leave-balances` command and termination balance policy config (HR-053 / HR-055).
- `Hr::missions()` facade entry (HR-059).
- Leave + Mission Feature tests (HR-060).

### Changed

- `Hr` facade/root class resolves domain services from the container instead of `new`-ing them; services are registered as singletons in `HrServiceProvider` (HR-007).
- `DocumentLockedException` now extends `HrException`.
- `BaseModel::getTable()` no longer double-prefixes after Eloquent `newInstance()` copies the table name.
- Hard-enforced employee status transitions: status updates must go through lifecycle service methods (HR-022).
- Security model documentation in `docs/USAGE.md` (HR-019).
- `Department::updatePath()` runs in a transaction; cycle prevention and soft-delete-with-children guard (HR-024–HR-026).
- `EmployeeService::assignPosition()` atomically closes the previous primary assignment, validates branch alignment (with override), and checks `hr_document_id` (HR-031 / HR-032).
- `Holiday::scopeForDate()` uses `whereDate` for reliable SQLite/MySQL comparisons.

### BREAKING

- `Karnoweb\Hr\Hr` now requires an `Illuminate\Contracts\Foundation\Application` constructor argument. Resolve it via the container / facade (`Hr::…` / `app('hr')`); do not `new Hr()` manually.
- Direct `$employee->update(['status' => …])` throws `InvalidEmployeeLifecycleException`; use `EmployeeService::terminate()` / `reactivate()` / `suspend()`.
- `hr.employee_code.sequence_per_branch=true` without `{branch}` in `format` throws `InvalidArgumentException`.
- Creating a second employee for the same employable (or duplicate non-null `national_id`) fails with `EmployeeAlreadyExistsException` / unique constraint.
- Catalog `code` columns are unique per `(branch_id, code)` instead of globally unique; global (`branch_id = NULL`) codes remain globally unique (HR-023).
- Do not create active contracts via `Contract::create()` without managing `active_key`; use `Hr::contracts()->hire()` / `renew()`.
- Assign employee shifts via `Hr::shiftAssignments()` so `current_key` is managed; do not leave multiple active assignments per employee.

## [13.0.0] - 2026-07-08

### Added

- Laravel 13 support (dedicated release line).

### Changed

- Minimum PHP version raised to 8.3.
- Illuminate packages now require `^13.0`.

### Notes

- For Laravel 10–12, continue using the `^1.0` release line.
