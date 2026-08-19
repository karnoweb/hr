# Implementation Checklist — Phase 5: Overtime

Part of the Karnoweb HR audit. See `HR-AUDIT.md` for the index. Depends on Phase 3 (AttendanceService).

- [ ] HR-061 — Create `OvertimeService` deriving `calculated_minutes` from `AttendanceRecord` (P0)
  - Area: new `src/Services/OvertimeService.php`
  - Acceptance:
    - given an `AttendanceRecord` with `overtime_minutes`/`overtime_night_minutes`/`overtime_holiday_minutes` populated by `AttendanceService` (Phase 3), generates or updates a matching `OvertimeRecord` with the correct type classification

- [ ] HR-062 — Implement night-window classification using `config('hr.overtime.night_start'/'night_end')` (P0)
  - Area: `OvertimeService`
  - Acceptance:
    - minutes worked within the configured night window are classified `OvertimeType::Night` rather than `Regular`
    - correctly handles a window crossing midnight (default `22:00`–`06:00`)
    - unit-tested

- [ ] HR-063 — Enforce `min_minutes` threshold before counting overtime (P1)
  - Area: `OvertimeService`
  - Acceptance:
    - overtime under `config('hr.overtime.min_minutes')` is not recorded/counted
    - tested at and around the exact boundary

- [ ] HR-064 — Enforce `monthly_cap` on approved overtime minutes per employee (P0)
  - Area: new `OvertimeService::approve()`
  - Acceptance:
    - approving an overtime record that would push the employee's month-to-date approved overtime past `config('hr.overtime.monthly_cap')` is rejected (or capped, per an explicit, documented policy decision — not a silent truncation)

- [ ] HR-065 — Add a proper enum cast for `OvertimeRecord.status` (P1)
  - Area: `src/Models/OvertimeRecord.php`, new `OvertimeStatus` enum
  - Acceptance:
    - model gains a cast consistent with every other status column in the codebase
    - existing string values (`'pending'`/`'approved'`) map cleanly onto the new enum's cases with no data migration needed

- [ ] HR-066 — Implement `requires_pre_approval` gating (P2)
  - Area: `OvertimeService`
  - Acceptance:
    - when `config('hr.overtime.requires_pre_approval')` is true, overtime cannot be recorded/approved without a linked, already-approved `OvertimeApproval`-type `HrDocument` created before the work occurred
    - documented and tested

- [ ] HR-067 — Expose `OvertimeService::approvedMinutesForPeriod()` for Phase 8's payroll calculator (P1)
  - Area: `OvertimeService`
  - Acceptance:
    - `approvedMinutesForPeriod(Employee $employee, PayrollPeriod $period): array` (keyed by `OvertimeType`) exists and is unit-tested, ready for Phase 8 to consume without re-deriving the aggregation logic

- [ ] HR-068 — Write the Overtime domain test suite, including a unit test for the existing `OvertimeType::rate()` helper (P0)
  - Area: `tests/Unit/`, `tests/Feature/`
  - Acceptance:
    - `OvertimeType::rate()` is unit-tested today (this can be done immediately, independent of any other Phase 5 item, since the method already contains real logic)
    - night classification, `min_minutes` threshold, and `monthly_cap` are each covered
