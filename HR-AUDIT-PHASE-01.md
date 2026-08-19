# Implementation Checklist — Phase 1: Core Employee Domain

Part of the Karnoweb HR audit. See `HR-AUDIT.md` for the index. Depends on Phase 0 (`HR-005` sequence generator, `HR-008` exceptions).

- [x] HR-011 — Make employee-code generation concurrency-safe using the Phase-0 sequence generator (P0)
  - Area: `EmployeeService::generateEmployeeCode()` (`src/Services/EmployeeService.php:109-145`), `hr_sequences`
  - Acceptance:
    - concurrent `createForUser()` calls never produce a duplicate `employee_code`
    - a concurrency test with parallel calls asserts N unique codes generated for N calls
    - existing format behavior (`{year}-{sequence}`, `sequence_length`, `sequence_per_year`) is preserved for single-threaded callers

- [x] HR-012 — Fix the `employee_code` format vs. `sequence_per_branch` conflict (P1)
  - Area: `config/hr.php:41-47`, `EmployeeService::generateEmployeeCode()`
  - Acceptance:
    - enabling `sequence_per_branch` without a `{branch}` placeholder in `format` either (a) auto-includes the branch in the generated code, or (b) throws a clear configuration exception at generation time
    - a test proves the previously-possible cross-branch collision scenario no longer silently occurs

- [x] HR-013 — Add a unique index on `employees(employable_type, employable_id)` (P1)
  - Area: new migration
  - Acceptance:
    - a second `Employee` cannot be linked to an already-linked `employable` model
    - `EmployeeService::createForUser()` surfaces the violation as a clear `EmployeeAlreadyExistsException` (extending `HrException` from HR-008), not a raw `QueryException`

- [x] HR-014 — Add a unique index on `employees.national_id` (nullable-safe) (P1)
  - Area: new migration
  - Acceptance:
    - two employees cannot share the same non-null `national_id`
    - existing/legitimate `NULL` rows are unaffected (standard unique-index `NULL` semantics)

- [x] HR-015 — Implement `EmployeeService::terminate()` with defined, atomic side effects (P0)
  - Area: `EmployeeService`, `Employee` model
  - Acceptance:
    - sets `status = Terminated` and `termination_date`
    - inside one transaction, also closes out the current `Contract`, current `EmployeePosition`, and current `EmployeeSalary` (sets `end_date`)
    - cancels pending `LeaveRequest`/`MissionRequest`/`Loan`/`DocumentApproval` rows assigned to or belonging to the employee
    - the whole operation is all-or-nothing (a failure partway through rolls back everything)
    - a Feature test asserts every one of these side effects independently

- [x] HR-016 — Implement `EmployeeService::reactivate()` (rehire) with an explicit `employee_code` reuse policy (P1)
  - Area: `EmployeeService`
  - Acceptance:
    - a documented, deliberate decision on whether a rehired employee keeps or regenerates `employee_code`
    - sets `status` back to `Active` and clears/updates `termination_date` per the documented policy
    - tested for both a same-year and cross-year rehire

- [x] HR-017 — Implement `EmployeeService::suspend()`, distinct from `terminate()` (P2)
  - Area: `EmployeeService`
  - Acceptance:
    - sets `status = Suspended` without triggering any of `terminate()`'s side effects (contract/position/salary remain open)
    - the difference from `terminate()` is documented in the method's docblock

- [x] HR-018 — Validate `hire_date <= termination_date` when both are present (P1)
  - Area: `Employee` model or `EmployeeService`
  - Acceptance:
    - attempting to set a `termination_date` earlier than `hire_date` throws a clear validation exception
    - covered by a unit test

- [x] HR-019 — Document that authorization/branch-scoping of `Employee` access is the integrating application's responsibility (P1)
  - Area: `docs/USAGE.md`, `EmployeeService` class docblock
  - Acceptance:
    - a "Security model" section in `docs/USAGE.md` states this explicitly and references the audit's Security & Authorization findings

- [x] HR-020 — Add an Iranian national-ID checksum validation helper (opt-in, not force-applied) (P2)
  - Area: new `src/Rules/` or `src/Support/` helper
  - Acceptance:
    - a pure function/validation rule validates the standard 10-digit Iranian national-ID checksum
    - unit-tested with known valid and invalid examples
    - not automatically invoked inside `EmployeeService` (left opt-in, since some integrators may have non-Iranian employees)

- [x] HR-021 — Write the Employee domain test suite (P0)
  - Area: `tests/Feature/EmployeeTest.php`, `tests/Unit/*`
  - Acceptance:
    - covers `createForUser()` happy path, wrong-user-class rejection, employee-code concurrency (HR-011), `terminate()` side effects (HR-015), `reactivate()` (HR-016), and `employable` uniqueness (HR-013)

- [x] HR-022 — Guard against `EmployeeStatus` transitions bypassing lifecycle methods (P2)
  - Area: `Employee` model
  - Acceptance:
    - a documented, explicit decision (soft guidance vs. hard enforcement) on whether direct `->update(['status' => ...])` outside of `terminate()`/`reactivate()`/`suspend()` is blocked or merely discouraged
    - whichever is chosen is implemented and tested
