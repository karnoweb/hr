# Implementation Checklist — Phase 6: Salary

Part of the Karnoweb HR audit. See `HR-AUDIT.md` for the index. Depends on Phase 0 (`HR-006` current-record pattern).

- [x] HR-069 — Implement "exactly one current salary per employee" as a DB-enforced invariant (P0)
  - Area: new migration on `hr_employee_salaries`, using the Phase-0 current-record pattern
  - Acceptance:
    - matches HR-027/HR-030's pattern exactly
    - concurrent attempts to set two current salaries for one employee are rejected at the database level
    - a Database test proves this directly

- [x] HR-070 — Create `SalaryService::changeSalary()` atomically closing the previous current salary and opening the new one (P0)
  - Area: new `src/Services/SalaryService.php`
  - Acceptance:
    - wrapped in a transaction with a lock on the employee's current-salary state
    - sets `end_date` on the old row
    - optionally links to an `HrDocument` (`SalaryChange` type)
    - a concurrency test with two parallel "raise" operations for the same employee proves only one current salary survives

- [x] HR-071 — Implement `CalculationType::Fixed` evaluation for `EmployeeSalaryItem`/`SalaryStructureItem` (P0)
  - Area: new `src/Services/SalaryCalculator.php`
  - Acceptance:
    - given an employee's current salary + structure + item overrides, correctly resolves each item's Fixed value with a documented precedence order (override > structure default > item `default_value`)
    - unit-tested against all three precedence levels

- [x] HR-072 — Implement `CalculationType::Percentage` evaluation, defining what `percentage_of` refers to (P0)
  - Area: `SalaryCalculator` + validation on `SalaryItem.percentage_of`
  - Acceptance:
    - `percentage_of` is validated to reference an existing `SalaryItem.code` at creation time (not left as an unvalidated free string)
    - percentage calculation correctly resolves the referenced item's computed value first, then applies the percentage
    - circular `percentage_of` references (A% of B, B% of A) are detected and rejected

- [x] HR-073 — Implement `CalculationType::Formula` evaluation with a safe, restricted expression evaluator (P1)
  - Area: `SalaryCalculator`
  - Acceptance:
    - `formula` strings are evaluated via a safe, sandboxed expression parser (explicitly **not** PHP `eval()`) supporting arithmetic and references to other named items
    - unsupported/unsafe expressions are rejected at `SalaryItem` creation time with a clear validation error, not discovered later at calculation time

- [x] HR-074 — Verify historical salary data remains queryable and unaffected by `changeSalary()` (P1)
  - Area: tests only (behavior implied by HR-070's design)
  - Acceptance:
    - a Feature test asserts `$employee->salaries` (all rows) still contains the prior current salary with `is_current = false` and a populated `end_date` after a change

- [x] HR-075 — Expose `SalaryService` through the `Hr` facade (P2)
  - Area: `src/Hr.php`
  - Acceptance:
    - `Hr::salaries()` (or similar) reaches `SalaryService` consistently with the rest of the facade

- [x] HR-076 — Propagate `SalaryItem.is_taxable`/`is_insurable` into `SalaryCalculator`'s output structure (P0)
  - Area: `SalaryCalculator`
  - Acceptance:
    - the calculator's output distinguishes taxable vs. non-taxable and insurable vs. non-insurable amounts per item, ready for Phase 8/9 to consume without re-deriving this from raw `SalaryItem` lookups

- [x] HR-077 — Add contract-type-aware salary eligibility rules, or explicitly defer (P3)
  - Area: `SalaryService`
  - Acceptance:
    - either implemented with a documented business rule (e.g. different treatment for `PartTime`/`Internship`), or explicitly marked **NEEDS VERIFICATION** / deferred with a one-line rationale — no invented, unrequested rule
  - **Deferred** in `SalaryService` docblock — NEEDS VERIFICATION.

- [x] HR-078 — Write the Salary domain test suite (P0)
  - Area: `tests/Feature/`, `tests/Unit/`, `tests/Concurrency/`
  - Acceptance:
    - covers the current-salary invariant + concurrency (HR-069/070), Fixed/Percentage/Formula calculation (HR-071–073), and historical-data preservation (HR-074)
