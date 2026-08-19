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

## Sequence allocation

All business sequence numbers (employee codes, document numbers, …) go through
`Karnoweb\Hr\Support\SequenceGenerator` and the `hr_sequences` table. Do not
add new `max()+1` / `count()+1` generators.
