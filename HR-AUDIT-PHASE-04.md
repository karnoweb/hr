# Implementation Checklist — Phase 4: Leave + Mission

Part of the Karnoweb HR audit. See `HR-AUDIT.md` for the index. Depends on Phase 0 (`HR-008` exceptions) and Phase 3 (`HR-041` WorkingDayCalculator).

- [x] HR-047 — Implement leave-balance validation in `LeaveService::request()` (P0)
  - Area: `src/Services/LeaveService.php:28-44`
  - Acceptance:
    - `request()` reads the employee's `LeaveBalance` for the relevant year/type and rejects a request whose `days`/`hours` exceed `remaining_days`, throwing `InsufficientLeaveBalanceException` (HR-008)
    - wrapped in a transaction with `lockForUpdate()` on the balance row
    - a concurrency test with two simultaneous requests that individually fit but combined exceed the balance proves the second is correctly rejected, not both accepted

- [x] HR-048 — Implement leave-request overlap detection (P0)
  - Area: `LeaveService::request()`
  - Acceptance:
    - a new request overlapping an existing `Pending` or `Approved` request's date range for the same employee is rejected
    - tested for exact overlap, partial overlap, and adjacent-but-not-overlapping (allowed) ranges

- [x] HR-049 — Implement working-day/holiday-aware day counting using the shared `WorkingDayCalculator` (P0)
  - Area: `LeaveService`
  - Acceptance:
    - `days` is computed from `start_date`/`end_date` minus weekends/holidays by default (explicit caller override still permitted)
    - tested against a range spanning both a holiday and a weekend

- [x] HR-050 — Enforce `fixed_days` + `once_per_employment` for marriage/bereavement-style leave types (P0)
  - Area: `LeaveService`
  - Acceptance:
    - a second request for a config-flagged `once_per_employment` leave type for the same employee is rejected
    - `fixed_days` types validate a caller-supplied `days` against the configured value

- [x] HR-051 — Enforce `max_hours_per_month` for hourly leave (P0)
  - Area: `LeaveService`
  - Acceptance:
    - summing approved hourly-leave hours for the current month and rejecting a request that would exceed `config('hr.leave.types.hourly.max_hours_per_month')`

- [x] HR-052 — Enforce `requires_document` at approval time for sick leave (P1)
  - Area: `LeaveService`/`DocumentService` integration
  - Acceptance:
    - a `requires_document`-flagged leave request cannot be approved (once Phase 11's workflow lands) without at least one `DocumentAttachment` on its linked `HrDocument`

- [x] HR-053 — Implement `carry_over`/`carry_over_max` application at year-boundary (P1)
  - Area: new `src/Services/LeaveBalanceService.php` + a year-end scheduled command
  - Acceptance:
    - the next year's `LeaveBalance.carried_days` correctly derives from the prior year's `remaining_days`, capped at `carry_over_max`
    - tested for both under-cap and over-cap scenarios

- [x] HR-054 — Implement `LeaveService::approve()`/`reject()`/`cancel()` with atomic balance decrement on approval (P0)
  - Area: `LeaveService`
  - Acceptance:
    - `approve()` decrements `LeaveBalance.used_days`/`remaining_days` atomically (locked), only for `paid` leave types
    - `cancel()` is only allowed for `Pending`, or `Approved`-but-not-yet-started (per a documented window), and reverses any already-applied decrement
    - all three methods are transactional

- [x] HR-055 — Implement termination-time leave-balance handling (P1)
  - Area: `EmployeeService::terminate()` (Phase 1, HR-015) + `LeaveBalanceService`
  - Acceptance:
    - a documented, config-driven policy (forfeit / pay-out / informational carry) is applied automatically on termination
    - tested against the chosen policy

- [x] HR-056 — Implement `MissionRequest` overlap detection (with other missions and with leave) (P1)
  - Area: new `src/Services/MissionService.php`
  - Acceptance:
    - overlapping mission requests for the same employee are rejected
    - a mission overlapping an approved leave request is rejected or flagged per a documented policy

- [x] HR-057 — Wire `MissionRequest` into `DocumentType::Mission` / the workflow engine (P1)
  - Area: `MissionService` + `DocumentService` integration
  - Acceptance:
    - creating a mission request can create a linked `HrDocument` routed through the workflow, matching the existing Leave↔Document pattern

- [x] HR-058 — Auto-mark `AttendanceRecord.status` for approved leave/mission date ranges (P1)
  - Area: `AttendanceService` integration hook, triggered from leave/mission approval
  - Acceptance:
    - approving a leave/mission request creates/updates `AttendanceRecord` rows for the covered working days with the correct status via `updateOrCreate` (never violating the `unique(employee_id, date)` constraint)

- [x] HR-059 — Expose full Leave + new Mission operations through the `Hr` facade (P2)
  - Area: `src/Hr.php`
  - Acceptance:
    - approve/reject/cancel for leave and the new `MissionService` are reachable via `Hr::leave()`/`Hr::missions()` consistently with the rest of the facade

- [x] HR-060 — Write the Leave + Mission domain test suite (P0)
  - Area: `tests/Feature/`, `tests/Concurrency/`
  - Acceptance:
    - covers every rule in HR-047 through HR-058 with at least one happy-path and one rejection test each
    - includes the balance-race concurrency test from HR-047
