# Implementation Checklist — Phase 3: Attendance + Shifts

Part of the Karnoweb HR audit. See `HR-AUDIT.md` for the index. Depends on Phase 0.

- [x] HR-035 — Create `AttendanceService` with `clockIn()`/`clockOut()` deriving `work_minutes`/`late_minutes`/`early_leave_minutes` from the employee's resolved `Shift` (P0)
  - Area: new `src/Services/AttendanceService.php`
  - Acceptance:
    - `clockIn()` creates (or safely reuses, respecting `unique(employee_id, date)`) today's record
    - `clockOut()` computes `work_minutes` and late/early-leave minutes against the resolved shift's `start_time`/`end_time` using `config('hr.attendance.late_tolerance_minutes')`/`early_leave_tolerance_minutes`
    - unit-tested against known shift/clock-time combinations, including exactly-at-tolerance-boundary cases

- [x] HR-036 — Implement shift resolution: "what shift does employee X work on date Y" (P0)
  - Area: new `src/Services/ShiftResolver.php`
  - Acceptance:
    - given a direct `Shift` assignment, returns it for any date within `[effective_date, end_date]`
    - given a `ShiftPattern` assignment, correctly computes the pattern-day index from `pattern_start_date`/`cycle_days`/the `pattern` JSON and returns the resolved shift or day-off
    - unit-tested against multiple cycle lengths (e.g. 7-day, 14-day patterns)

- [x] HR-037 — Define and implement an explicit overnight-shift date-boundary policy (P0)
  - Area: `AttendanceService`, `Shift.is_night_shift`
  - Acceptance:
    - a documented, tested decision on which calendar `date` an overnight attendance record belongs to (e.g. clock-in date)
    - legitimate overnight attendance does not trip the `unique(employee_id, date)` constraint

- [x] HR-038 — Apply `auto_clock_out`/`auto_clock_out_after_hours` via a scheduled command (P1)
  - Area: new `src/Console/Commands/AutoClockOutCommand.php`
  - Acceptance:
    - when `config('hr.attendance.auto_clock_out')` is enabled, employees with an open `clock_in` older than the configured threshold are automatically clocked out with a flagged `source`/note
    - registered on the scheduler; covered by a Feature test

- [x] HR-039 — Prevent overlapping active `EmployeeShiftAssignment` rows per employee (P1)
  - Area: new migration/app-level validation, using the Phase-0 current-record pattern
  - Acceptance:
    - assigning a new active shift/pattern to an employee atomically closes the previous active assignment (`end_date`)
    - concurrency-tested with two parallel assignment calls

- [x] HR-040 — Wire `Holiday` into `AttendanceService` (auto-mark holiday-dated records) (P1)
  - Area: `AttendanceService`
  - Acceptance:
    - attendance records for a date returned by `Holiday::forBranch()->forDate()` default to `AttendanceStatus::Holiday` unless explicit clock data overrides it
    - tested against both global and branch-specific holidays, including the same-date dual-row edge case

- [x] HR-041 — Implement a shared `WorkingDayCalculator` (working days in a range, minus weekends, minus holidays) (P0)
  - Area: new `src/Support/WorkingDayCalculator.php` (shared across Attendance/Leave/Payroll — not duplicated per domain)
  - Acceptance:
    - given a date range, branch, and `config('hr.working_days')`, returns the correct working-day count and list
    - correctly de-duplicates the global-plus-branch-specific same-date holiday edge case
    - extensively unit-tested

- [x] HR-042 — Add a manual-adjustment audit trail for `AttendanceRecord` corrections (P2)
  - Area: `AttendanceService`
  - Acceptance:
    - editing an existing attendance record through the service records who/when/what-changed
    - direct Eloquent updates bypassing the service are explicitly documented as out of scope for this audit trail

- [x] HR-043 — Implement an approval flow for manual attendance corrections requiring approval (P2)
  - Area: `AttendanceService` + `DocumentType` (reuses the Phase 11 workflow fix once it lands)
  - Acceptance:
    - a correction above a configurable threshold (or any correction, per config) creates an `HrDocument` routed through the workflow engine

- [x] HR-044 — Add `ShiftPattern` JSON structure validation against `cycle_days` (P2)
  - Area: `ShiftPattern` model or a dedicated validation rule
  - Acceptance:
    - creating/updating a `ShiftPattern` whose `pattern` array length doesn't match `cycle_days` is rejected with a clear validation error

- [x] HR-045 — Verify `shifts.code`/`shift_patterns.code` branch scoping is covered (cross-reference HR-023) (P0)
  - Area: cross-reference to HR-023
  - Acceptance: verified covered by HR-023; exists only for this phase's self-contained review

- [x] HR-046 — Write the Attendance + Shift domain test suite (P0)
  - Area: `tests/Feature/`, `tests/Unit/`
  - Acceptance:
    - covers clock-in/out happy path, late/early computation, shift-pattern resolution (multiple cycle lengths), overnight-shift boundary policy, duplicate-attendance rejection (Database test against the existing constraint), holiday interaction, and working-day calculator correctness
