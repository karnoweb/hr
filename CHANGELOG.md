# Changelog

## [Unreleased]

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
