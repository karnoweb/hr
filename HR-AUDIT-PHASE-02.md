# Implementation Checklist — Phase 2: Organization & Contracts

Part of the Karnoweb HR audit. See `HR-AUDIT.md` for the index. Depends on Phase 0 (`HR-006` current-record pattern, `HR-008` exceptions).

- [x] HR-023 — Scope `code` uniqueness by `branch_id` across `departments`, `positions`, `shifts`, `shift_patterns`, `salary_items`, `salary_structures` (P0)
  - Area: 6 new migrations
  - Acceptance:
    - two branches can each have a record with the same `code` in every one of these six tables
    - a global (`branch_id = NULL`) record still cannot collide with another global record of the same `code`
    - each migration includes a documented pre-flight check/plan for any existing duplicate codes before the new constraint is applied

- [x] HR-024 — Prevent `Department.parent_id` cycles (P1)
  - Area: `Department` model (creating/updating event) or a new `DepartmentService`
  - Acceptance:
    - attempting to set a department's `parent_id` to one of its own descendants throws a clear exception
    - unit test covers at least a 3-level cycle attempt

- [x] HR-025 — Fix `Department` soft-delete / `parent_id` `nullOnDelete()` interaction (P1)
  - Area: `Department` model, `src/Models/Department.php:70-78`
  - Acceptance:
    - a regression test first documents today's broken behavior (soft-deleting a parent silently causes `updatePath()` to mis-root its children)
    - then either: soft-deleting a department with non-deleted children is blocked with a clear exception, or `updatePath()` is made soft-delete-aware (uses `withTrashed()` when resolving `parent`)
    - the regression test then passes against the fix

- [x] HR-026 — Wrap `Department::updatePath()`'s subtree cascade in a transaction (P2)
  - Area: `Department` model
  - Acceptance:
    - a test that forces an exception on one child during the cascade asserts no siblings were updated either (all-or-nothing)

- [x] HR-027 — Implement "exactly one active contract per employee" as a DB-enforced invariant (P0)
  - Area: new migration on `hr_contracts`, using the Phase-0 current-record pattern (HR-006)
  - Acceptance:
    - inserting a second active/current contract for an employee without first closing the existing one fails at the database level
    - documented and covered by a Database test

- [x] HR-028 — Create `ContractService` with `hire()`, `renew()`, `extend()`, `terminate()` methods (P0)
  - Area: new `src/Services/ContractService.php`
  - Acceptance:
    - `renew()`/`extend()`/`terminate()` atomically close out the previous current contract (`end_date` + status transition) and, where applicable, insert the new one, inside one transaction under a lock (per the Concurrency file's required solution)
    - overlap between the closed contract's and the new contract's date ranges is validated
    - a concurrency test with two parallel "renew" calls for the same employee proves only one active contract survives

- [x] HR-029 — Add a uniqueness constraint on `contracts.contract_number` (P1)
  - Area: new migration
  - Acceptance:
    - duplicate contract numbers across any employees are rejected at the DB level

- [x] HR-030 — Implement "exactly one current primary `EmployeePosition` per employee" as a DB-enforced invariant (P0)
  - Area: new migration on `hr_employee_positions`, using the Phase-0 current-record pattern
  - Acceptance:
    - same class of test as HR-027, scoped to `(employee_id)` where `is_primary = 1 AND end_date IS NULL`

- [x] HR-031 — Extend `EmployeeService::assignPosition()` to atomically close the previous current position (P0)
  - Area: `src/Services/EmployeeService.php:80-101`
  - Acceptance:
    - calling `assignPosition()` twice for the same employee results in exactly one current primary `EmployeePosition` (the old one gets `end_date` set)
    - wrapped in a transaction with a lock on the employee's current-position state
    - a concurrency test with two parallel calls proves only one "current" row survives

- [x] HR-032 — Validate assigned `department_id`/`position_id` belong to the employee's `branch_id` (or explicitly allow cross-branch via an override flag) (P1)
  - Area: `EmployeeService::assignPosition()`
  - Acceptance:
    - a clear, documented decision is implemented: either reject cross-branch assignment by default, or allow it only via an explicit, named options flag
    - tested for both the default and override paths

- [x] HR-033 — Resolve the missing-FK gap on `hr_document_id` columns across `employee_positions`, `contracts`-adjacent tables, `employee_salaries`, `loans`, `overtime_records`, `leave_requests`, `mission_requests`, `payroll_records` (P2)
  - Area: new migrations
  - Acceptance:
    - either a real foreign key constraint is added (with migration ordering resolved) or an app-level validation is added at every write site with a test proving an invalid `hr_document_id` is rejected
    - the decision made is documented in a code comment at each affected model

- [x] HR-034 — Write the Organization + Contract + EmployeePosition domain test suite (P0)
  - Area: `tests/Feature/`, `tests/Database/`
  - Acceptance:
    - covers branch-scoped code uniqueness (HR-023), cycle prevention (HR-024), soft-delete interaction (HR-025), one-active-contract invariant + concurrency (HR-027/028), one-current-position invariant + concurrency (HR-030/031)
