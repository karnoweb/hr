# Architecture notes (Karnoweb HR)

Short, living notes for contributors. Expand as Phases 1–14 land.

## Exactly one current record (MySQL-compatible unique pattern)

Several HR entities must have **at most one "current" row per owner**:

- one current salary per employee (`employee_salaries`)
- one current primary position per employee (`employee_positions`)
- One active contract per employee (`contracts`)
- One current primary shift assignment per employee (`employee_shift_assignments`, Phase 3)

MySQL (and SQLite / PostgreSQL under common unique-index semantics) treats
**multiple `NULL` values as distinct** in a unique index. That lets us enforce
"at most one non-null current key" without partial indexes:

```sql
-- Conceptual shape (applied per-domain in later migrations, Phases 2 & 6)
ALTER TABLE hr_employee_salaries
  ADD COLUMN current_key BIGINT UNSIGNED NULL,
  ADD UNIQUE INDEX hr_employee_salaries_current_key_unique (current_key);

-- When a row IS current:
UPDATE hr_employee_salaries SET current_key = employee_id, is_current = 1 WHERE id = ?;

-- When a row is historical / closed:
UPDATE hr_employee_salaries SET current_key = NULL, is_current = 0 WHERE id = ?;
```

Rules for every domain that adopts this pattern:

1. Add a nullable `current_key` column (same type as the owner id, typically `employee_id`).
2. Add a **unique** index on `current_key` alone.
3. Set `current_key = <owner_id>` only on the single current row; all other rows for that owner must keep `current_key = NULL`.
4. Flip old → new inside **one** `DB::transaction()` with `lockForUpdate()` on the previous current row (see concurrency audit).
5. Keep any existing boolean like `is_current` / `is_primary` as a query convenience if useful — the **database invariant** is the unique `current_key`, not the boolean.

Do **not** invent a different per-domain uniqueness trick. Phases 2 (contracts / positions) and 6 (salaries) must follow this ADR exactly.

## Layer boundaries (where new code goes)

| Layer | Namespace / path | Put here when… |
|-------|------------------|----------------|
| **Service** | `src/Services/` | Orchestrating a use case: transactions, locking, calling calculators, emitting events, enforcing lifecycle rules (`PayrollService`, `DocumentService`, …). |
| **Calculator** | `src/Calculators/` or `*Calculator` in Services | Pure or mostly pure computation from already-loaded inputs (insurance/tax brackets, salary line items, overtime minutes). No HTTP, no auth. |
| **Support** | `src/Support/` | Shared non-domain utilities (`SequenceGenerator`, `PayrollBatchContext`, expression evaluators). |
| **Event** | `src/Events/` | Facts the host app (e.g. accounting) should react to — **no** listener that calls external packages from HR. |
| **Exception** | `src/Exceptions/` | Typed, catchable domain failures (`PayrollPeriodLockedException`, `UnauthorizedApprovalException`, …). |
| **Model** | `src/Models/` | Persistence, relations, scopes — **not** multi-step business workflows. |
| **Enum** | `src/Enums/` | Fixed vocabularies with helpers (`canEdit()`, `label()`, …). |

**Rules of thumb:**

1. Controllers / jobs in the **host app** call `Hr::…()` services; they do not duplicate service rules via raw `Model::create()` for governed entities (salary, documents, loans, payroll periods).
2. Cross-package integration uses **events + docs**, not hard dependencies (see `docs/ACCOUNTING.md`).
3. Concurrency-sensitive sequences use `SequenceGenerator`, not `max()+1`.
4. Authorization is mostly **deferred to the host app** except document approval actor checks — document in `docs/USAGE.md` and `tests/Security/`.

## Sequence allocation

All business sequence numbers (employee codes, document numbers, …) go through
`Karnoweb\Hr\Support\SequenceGenerator` and the `hr_sequences` table. Do not
add new `max()+1` / `count()+1` generators.
