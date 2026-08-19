# 3. Domain Audit

Part of the Karnoweb HR audit. See `HR-AUDIT.md` for the index and executive summary.

Each domain is scored **Production readiness**: Not started / Schema-only / Partial / Ready.

---

## Organization (Department, Position, Employee Position)

**Current implementation:** `Department` (self-referencing tree via `parent_id`, auto-maintained `path`/`level` via `booted()` hooks — `src/Models/Department.php:58-78`), `Position` (flat catalog with `grade`), `EmployeePosition` (history/pivot table linking employee → department + position with `effective_date`/`end_date`).

**Only represented by schema:** Branch-level scoping. Every organization table has a nullable `branch_id` column but there is no `Branch` model in the package (by design — `config('hr.models.branch')` defaults to `null`, `config/hr.php:12`), no relationship helper for it, and — critically — **no branch-scoped uniqueness**.

**Business logic:** `Department::updatePath()` recursively recomputes `path`/`level` for the whole subtree on create/parent-change. `EmployeeService::assignPosition()` creates an `EmployeePosition` row. That is the entire logic surface.

**Business rule gaps:**
- No department-move/organization-restructure workflow: reassigning an employee's department is just "insert another `EmployeePosition` row"; nothing closes out (`end_date`) the prior row, so `currentPosition()` may return whichever row happens to sort last by `effective_date`, potentially the wrong one if two rows share a date.
- No validation that a `Department`'s `parent_id` doesn't create a cycle.
- No branch consistency check between `Employee.branch_id`, `EmployeePosition.department_id`'s department's `branch_id`, and `Position.branch_id` — an employee in branch A can be assigned a position that belongs to branch B with nothing preventing it.

**Data integrity gaps:**
- `departments.code`, `positions.code` are **globally unique** (`database/migrations/...000001...php:17`, `...000002...php:16`) despite `branch_id` being a first-class scoping column on both tables. Concrete scenario: Branch A wants a department coded `IT` and Branch B independently wants a department also coded `IT` (a completely normal multi-branch pattern) — the second `Department::create(['code' => 'IT', 'branch_id' => 2, ...])` fails with a unique-constraint violation. This directly contradicts the "Multi-branch behavior" requirement.
- `Department` soft-deletes (`use SoftDeletes`, `src/Models/Department.php:12`) but its self-referencing FK is `nullOnDelete()` (`database/migrations/...000001...php:29-32`), which only fires on a **hard** delete. Soft-deleting a parent department leaves children's `parent_id` pointing at a row that Eloquent's default global scope now hides. `$department->parent` returns `null` (not the soft-deleted row), so if any child is saved again, `updatePath()` (`src/Models/Department.php:70-78`) treats it as a root department and silently rewrites its `path`/`level`, corrupting the tree.

**Security gaps:** No scoping/authorization anywhere — any caller can read/write any department/position across any branch; the package has no branch-context enforcement mechanism at all (this is arguably intentional decoupling, but it must be documented as a caller responsibility, which it currently is not).

**Concurrency gaps:** `updatePath()` does a `->update()` per node then recurses into `$this->children` without eager loading — for a moderately deep tree this is N synchronous queries with no locking; two concurrent moves of the same subtree can interleave and leave inconsistent `path` values (last-write-wins, no lock).

**Transaction gaps:** `updatePath()`'s cascading update across a subtree is **not wrapped in a transaction** — a failure partway through leaves some descendants updated and others not.

**Testing gaps:** No tests exist for tree consistency, cycle prevention, soft-delete interaction, or branch scoping.

**Integration points missing:** Nothing links `Department`/`Position` to Payroll (e.g., department-level cost center) or to Workflow's `department_head` approver type (see Workflow section — `ApproverType::DepartmentHead` has no resolution logic that would even need `Department` at all).

**Production readiness: Schema-only / Partial.** The tree-maintenance code is real and mostly works for the single-branch, hard-delete-only case; multi-branch and soft-delete interactions are broken.

**Exact remaining work:** branch-scope the unique constraints, guard against cycles, close out `EmployeePosition`/department assignment on reassignment, wrap `updatePath()` in a transaction, decide+implement a policy for soft-deleted parents.

---

## Employee

**Current implementation:** `Employee` model with polymorphic `employable` (any user-like model), `employee_code`, `status` (enum: Active/Suspended/Terminated), extensive personal/bank fields, soft deletes. `EmployeeService::createForUser()` and `findByUser()`. Employee-code auto-generation.

**Only represented by schema:** `termination_date` column exists but is **never set by any code path** — there is no `terminate()` method anywhere. `status` transitions (Active → Suspended → Terminated, or → Active again for reactivation) are not modeled at all; nothing prevents setting `status` directly to any value via a raw `update()`.

**Business logic:** Creation (`createForUser`) and code generation. That is all — no `terminate()`, `reactivate()`, `suspend()`, no lifecycle methods whatsoever.

**Business rules missing:**
- **Termination has zero side effects.** In a real ERP, terminating an employee should at minimum: end the current `Contract`, end the current `EmployeePosition`, end the current `EmployeeSalary`, cancel pending `LeaveRequest`/`MissionRequest`/`Loan`, and cancel any pending `DocumentApproval` assigned to them. None of this exists.
- **Reactivation** (rehire) is a listed `DocumentType::Rehire` enum case (`src/Enums/DocumentType.php:9`) but has no corresponding service logic distinguishing it from a fresh hire (e.g., should it reuse the old `employee_code`? Nothing decides this).
- No validation of `national_id` format/checksum (Iranian national ID has a checksum digit) despite this being an Iranian-context package.
- No enforcement that `hire_date <= termination_date` when both are set.

**Data integrity gaps:**
- No unique constraint on `national_id` or `insurance_number` (`database/migrations/...000003...php:22-23` — plain nullable strings). Two `Employee` records can be created for the same real person with the same national ID; nothing catches this, including at the `EmployeeService` layer.
- `employee_code` is globally unique (`...000003...php:18`) but the default format (`{year}-{sequence}`, `config/hr.php:43`) has **no `{branch}` placeholder**. If an integrator flips `sequence_per_branch` to `true` (a documented, supported config option, `config/hr.php:45`) without also customizing the format, two branches will legitimately compute the same `{year}-{sequence}` string for their respective first employee of the year and the second `INSERT` will fail on the global unique constraint — a config option that is broken by its own default interaction.

**Security gaps:** `createForUser()` throws if `$user` isn't an instance of `config('hr.models.user')` (`src/Services/EmployeeService.php:33-35`) — this is a legitimate/pending check, but nothing checks that the *caller* is authorized to create an employee record for an arbitrary `$user` (no ownership/role check — by design left to the consuming app, but this should be documented explicitly as a caller responsibility since it currently is not documented anywhere).

**Concurrency gaps:** Employee-code generation race (see Section 2, item 3) — this is the concrete, reproducible scenario: two HTTP requests both call `createForUser()` for a different new hire at nearly the same instant; both execute `Employee::query()->where('employee_code','like','1403-%')->orderByDesc('employee_code')->value('employee_code')` and get the same last code back (say `1403-0007`), both compute `1403-0008`, both attempt `Employee::create()`; the `employee_code` unique constraint means one of the two `INSERT`s throws a `QueryException`, so one of the two hire operations fails outright rather than retrying with the next sequence number.

**Edge cases missing:** re-hiring a previously terminated employee (soft-deleted or not); an employee with multiple `employable` records over time (e.g., re-issued user account) is not addressed by the polymorphic uniqueness (no unique index on `(employable_type, employable_id)` — only a plain composite index, `...000003...php:41`, so the same user could theoretically get two `Employee` rows).

**Test coverage:** none.

**Production readiness: Partial.** Creation works; the entire lifecycle past creation (status transitions, termination, reactivation) does not exist.

---

## Contract

**Current implementation:** `Contract` model only (`type`: enum `ContractType`, `status`: enum `ContractStatus`). `currentContract()` relation on `Employee` (`hasOne(...)->where('status','active')->latest('start_date')`, `src/Models/Employee.php:44-47`). **No `ContractService` exists at all.**

**Only represented by schema:** Everything except the one read-only relation above. Contract creation, renewal, extension, and termination all have zero code — they are pure `Contract::create([...])` as shown in `docs/USAGE.md:264-277`.

**Business logic:** None beyond the `currentContract()` read helper.

**Business rules missing:**
- No enforcement of "at most one active contract per employee" — `ContractStatus::Active` is just a string value with no accompanying invariant.
- No overlap detection between contracts (two `active` contracts with overlapping date ranges for the same employee are perfectly representable).
- No renewal/extension logic corresponding to the `ContractExtend`/`ContractEnd` `DocumentType` enum cases (`src/Enums/DocumentType.php:11-12`) — these enum values exist as vocabulary but nothing in the codebase ever creates or reacts to a document of that type.
- No link enforced between `Contract` and `EmployeeSalary`/`EmployeePosition` (e.g., a contract's `type` — e.g. `PartTime` — has no bearing on salary/position validation).

**Data integrity gaps:** No unique/partial-unique mechanism preventing multiple `active` contracts per employee (same class of bug as `EmployeeSalary.is_current`, just without even the non-unique index that salary has). `contract_number` is a plain nullable string with **no uniqueness constraint at all** (`...000004...php:16`) — duplicate contract numbers across employees are fully possible.

**Security gaps:** None beyond the general lack of any authorization layer.

**Concurrency gaps:** Creating a "new" contract for an employee who already has an `active` one is a classic check-then-insert race: read current active contract → decide to end it → insert new one, with no transaction or lock — except this code doesn't even exist yet, so today the race is simpler: nothing stops two concurrent inserts of `active` contracts.

**Transaction gaps:** N/A — no multi-step contract operation exists yet to have a boundary.

**Test coverage:** none.

**Integration points missing:** Contract termination should influence Employee `status`/`termination_date`, but no `ContractService` exists to do that. `Contract.type` should plausibly affect payroll/insurance eligibility (e.g., `Internship`/`PartTime` may have different insurance rules) — not modeled anywhere.

**Production readiness: Schema-only.** This is the single least-implemented "core" domain: a model, two enums, and one read relation.

---

## Employee Position (assignment/history)

**Current implementation:** `EmployeePosition` history table + `EmployeeService::assignPosition()` (`src/Services/EmployeeService.php:80-101`) which simply inserts a new row. `Employee::currentPosition()` filters `is_primary=true, end_date=null`, ordered by `effective_date` (`src/Models/Employee.php:59-62`).

**Business rules missing:**
- `assignPosition()` never closes the previous "current" assignment's `end_date`. Calling it twice for the same employee produces two rows with `is_primary=true` and `end_date=null`; `currentPosition()`'s `hasOne` will pick one deterministically (latest `effective_date`) but the *data* now represents two simultaneously-current primary positions, which is wrong per the domain (an employee should have exactly one current primary position).
- No validation that `department_id`/`position_id` belong to the employee's `branch_id`.
- No secondary/non-primary position handling logic (the `is_primary` flag exists but nothing in the service distinguishes creating a secondary assignment from a primary one, beyond the caller manually passing `is_primary: false`).

**Data integrity gaps:** No unique constraint enforcing "at most one row per employee with `is_primary=1 AND end_date IS NULL`" — same invariant-not-enforced pattern as Contract and EmployeeSalary. `hr_document_id` (`...000005...php:21`) has no foreign key at all (plain `unsignedBigInteger`), so it can point at a nonexistent or deleted `HrDocument`.

**Concurrency gaps:** `assignPosition()` is a single `INSERT` with no preceding "close out the old one" step, so there isn't yet a race in the traditional sense — but the *missing* close-out step means the correct future implementation (read current row, update its `end_date`, insert new row) will need explicit locking to avoid two concurrent department-change requests both reading the same "current" row and both trying to close it out / both leaving two new "current" rows.

**Production readiness: Schema-only / Partial.** Insert-only; no lifecycle management.

---

## Attendance

**Current implementation:** `AttendanceRecord` model (`clock_in`/`clock_out`, `work_minutes`, `late_minutes`, `early_leave_minutes`, `overtime_minutes` + night/holiday overtime split, `status` enum `AttendanceStatus`, `source`). **No `AttendanceService` exists.**

**Only represented by schema:** Literally everything computational. `docs/USAGE.md:362-380` shows the intended usage is to `AttendanceRecord::create([...])` with **all the derived fields (`work_minutes`, `late_minutes`, `overtime_minutes`) passed in manually by the caller** — there is no clock-in/clock-out pair of methods that computes these from a `Shift`.

**Business logic:** None. Not even a `clockIn()`/`clockOut()` helper.

**Business rules missing:** late/early-leave tolerance (`config('hr.attendance.late_tolerance_minutes')`, `early_leave_tolerance_minutes`, `config/hr.php:158-159`) is defined but **never read anywhere in `src/`** (confirmed via repo-wide `config(` search — these two keys have zero call sites). `min_work_hours`, `auto_clock_out`, `auto_clock_out_after_hours` (`config/hr.php:160-163`) are likewise defined and unused. Overnight-shift handling (a shift that starts one calendar day and ends the next) is entirely unaddressed — there is no logic deciding which `date` an overnight attendance record belongs to.

**Data integrity gaps:** `unique(['employee_id', 'date'])` (`...000010...php:32`) **is correctly DB-enforced** — this is a real, working invariant (one attendance record per employee per day) and should be preserved. However, for overnight shifts this single-record-per-date model is structurally ambiguous: a shift from 22:00 to 06:00 spans two calendar dates, and nothing in the schema or any (nonexistent) service decides whether that's "day 1's record with clock_out on day 2" or "two records" — the unique constraint will actively block whichever approach isn't chosen consistently.

**Concurrency gaps:** N/A today (no service exists that could race); will become relevant once clock-in/clock-out endpoints exist (double clock-in for the same day, concurrent auto-clock-out job vs. manual clock-out).

**Edge cases missing:** missing checkout (`clock_out` null forever), corrections/manual adjustment audit trail (no `DocumentHistory`-style trail for who edited an attendance record and why), holiday/weekend interaction with `Holiday`/`working_days` config (nothing cross-references `Holiday::forDate()`/`forBranch()` from attendance logic because there is no attendance logic).

**Integration points missing:** Attendance → Overtime (should auto-generate `OvertimeRecord` candidates from `overtime_minutes`, doesn't), Attendance → Payroll (`PayrollRecord.present_days`/`absent_days`/`late_minutes` should be aggregated from `AttendanceRecord`, isn't), Attendance → Shift (`AttendanceRecord.shift_id` is stored but nothing computes lateness relative to `Shift.start_time`/`end_time`).

**Production readiness: Schema-only.**

---

## Shift

**Current implementation:** `Shift` (fixed daily times, `is_night_shift` flag, `work_minutes`), `ShiftPattern` (rotating pattern via a `pattern` JSON column + `cycle_days`), `EmployeeShiftAssignment` (employee ↔ shift or pattern, with `effective_date`/`end_date`/`pattern_start_date`).

**Business logic:** None. No method anywhere resolves "what shift does employee X work on date Y" from a `ShiftPattern`'s `pattern` JSON + `cycle_days` + `pattern_start_date` — this is pure schema for a moderately complex rotating-shift feature.

**Business rules missing:** overnight-shift date-boundary handling (same issue as Attendance); no validation that `pattern` JSON's structure matches `cycle_days`; no validation preventing an employee from having two overlapping *active* `EmployeeShiftAssignment` rows (both a direct `shift_id` and a `shift_pattern_id` assignment active at once, or two direct shift assignments both `is_active=true`).

**Data integrity gaps:** No unique/exclusion constraint on `(employee_id, is_active)` for "current" shift assignment (same "current record" pattern as elsewhere, unenforced). `shifts.code`, `shift_patterns.code` are globally unique regardless of `branch_id` — the same multi-branch problem as Department/Position codes.

**Production readiness: Schema-only.**

---

## Leave

**Current implementation:** `LeaveRequest`, `LeaveBalance` models; `LeaveService::request()` (blind create) and `balance()` (blind read). Rich config in `config/hr.php:69-132` describing 7 leave types with very specific rules (`carry_over_max: 9`, `once_per_employment`, `max_hours_per_month: 12`, `fixed_days`, `requires_document`, `paid`).

**Business rules missing (all of the following config keys are defined and read by nothing):** `carry_over`, `carry_over_max`, `requires_document`, `once_per_employment`, `max_hours_per_month`, `fixed_days`, `paid` — confirmed via repo-wide search: the only two `hr.leave.*`/`hr.overtime.*`-style config reads are `Hr::config()` passthrough and `OvertimeType::rate()`; nothing under `hr.leave` is read anywhere in `src/`.
- **Balance is never validated or decremented.** `LeaveService::request()` never checks `LeaveBalance.remaining_days` before creating a request, and nothing anywhere decrements `used_days`/`remaining_days` when a leave request is approved. `LeaveBalance` is a pure, disconnected ledger that the integrator must maintain entirely by hand.
- **No overlap detection.** Two `LeaveRequest` rows for the same employee with overlapping `start_date`/`end_date` are fully representable; nothing checks.
- **No working-day/holiday-aware day counting.** `days`/`hours` are passed in verbatim by the caller (`docs/USAGE.md:59-65` shows `'days' => 3` as a literal, caller-supplied number) — there's no calculation from `start_date`/`end_date` minus weekends/holidays.
- **`hourly` leave type's `max_hours_per_month: 12` cap** is not enforced anywhere.
- **`marriage`/`maternity`'s `fixed_days`/`once_per_employment`** are not enforced — an employee could submit ten "marriage leave" requests.
- **Approval/rejection/cancellation** of a `LeaveRequest` has no dedicated logic; `LeaveRequestStatus` has a `Cancelled` case (`src/Enums/LeaveRequestStatus.php:10`) but no method sets it.
- **Employee termination effects**: no logic forfeits/prorates unused leave balance on termination (there's no termination logic at all — see Employee domain).

**Data integrity gaps:** `leave_balances` has `unique(['employee_id','year','type'])` (`...000014...php:26`) — correctly DB-enforced, a real strength. `leave_requests` has no overlap-prevention constraint (not realistically expressible as a simple DB unique index for date ranges, but nothing at the app level substitutes for it either).

**Concurrency gaps:** Once balance-decrement logic is written, "check balance → create request → (later) decrement on approval" is a classic race between two concurrent leave requests for the same employee/year/type that both pass the balance check before either commits.

**Test coverage:** none.

**Production readiness: Schema-only** (the `request()`/`balance()` methods are real but do not implement any of the rules the config file promises).

---

## Mission / Business Trips

**Current implementation:** `MissionRequest` model only — reuses `LeaveRequestStatus` for its `status` (a mild naming/primitive-obsession smell: a "mission" isn't a "leave", but functionally the four states — pending/approved/rejected/cancelled — are identical, so this is a low-priority nit, not a bug). No service at all; not even exposed through `Hr::` facade.

**Business rules missing:** overlap detection (with other missions, or with leave, or with attendance), no attendance interaction (a mission day should presumably mark `AttendanceRecord.status = Mission`, doesn't), `expenses` JSON column exists with zero validation/aggregation logic, no approval workflow wiring despite `DocumentType::Mission` existing and being in `config('hr.documents.require_approval')` (`config/hr.php:241`) — nothing actually creates an `HrDocument` for a `MissionRequest` (the integrator must do this manually per `docs/USAGE.md`, which never shows it for missions at all, unlike leave).

**Data integrity gaps:** No overlap constraint; no constraint tying `days` to `end_date - start_date`.

**Production readiness: Schema-only.**

---

## Overtime

**Current implementation:** `OvertimeRecord` model, `OvertimeType` enum with a `rate()` method that reads `config('hr.overtime.rates.*')` (`src/Enums/OvertimeType.php:20-27`) — this is the only enum in the codebase with an actual calculation helper, but it's a pure multiplier lookup, never invoked from anywhere.

**Business rules missing:** `monthly_cap: 120` (minutes? hours? unit is ambiguous even in config, `config/hr.php:147`) is never enforced; `night_start`/`night_end` window (`config/hr.php:145-146`) is never used to classify minutes as night overtime; `min_minutes: 30` threshold (below which overtime doesn't count) is never applied; `requires_pre_approval` flag is never read. `OvertimeRecord.status` has **no enum cast at all** (`src/Models/OvertimeRecord.php` casts array has no `status` entry) — inconsistent with every other status-bearing model in the codebase, which is a stringly-typed-state smell.

**Data integrity gaps:** no cast means arbitrary strings can be stored in `status` with zero type safety; `calculated_minutes` vs `approved_minutes` distinction exists in schema but nothing ever populates `calculated_minutes` from attendance data (it's caller-supplied per `docs/USAGE.md:388-395`).

**Integration points missing:** Attendance → Overtime (should derive `calculated_minutes` from `AttendanceRecord.overtime_minutes`), Overtime → Payroll (should feed `PayrollRecord.overtime_minutes`/earnings), Overtime → approval workflow (`DocumentType::OvertimeApproval` exists but nothing creates one from an `OvertimeRecord`).

**Production readiness: Schema-only.**

---

## Salary

**Current implementation:** `SalaryItem` (catalog of earning/deduction line items, `CalculationType`: fixed/formula/percentage), `SalaryStructure` + `SalaryStructureItem` (named bundles of items with default values), `EmployeeSalary` + `EmployeeSalaryItem` (per-employee assignment with overrides).

**Business rules missing:** the `formula` text column (`...000020...php:22`) and `percentage_of` column (`...000020...php:23`) have **no evaluator anywhere** — nothing parses/executes a `formula` string or resolves what "percentage of" refers to (percentage of base salary? of another item? unspecified even in schema — no FK, just a plain string). `CalculationType::Formula`/`::Percentage` are fully unimplemented; only `Fixed` could conceivably work today (by reading `default_value` directly), and even that isn't wired into anything since there's no `SalaryService`/calculator.

**Data integrity gaps — the headline issue:** `employee_salaries.is_current` has **only a plain composite index**, not a unique constraint (`...000022...php:24`, confirmed again here for completeness — see Section 2 item 8 for full detail). This is the single most consequential invariant gap in the whole package: `Employee::currentSalary()` (`src/Models/Employee.php:89-92`) is a `hasOne` with `where('is_current', true)` — if two rows have `is_current=true`, Eloquent's `hasOne` silently returns whichever the DB happens to return first (undefined without an explicit `orderBy`), meaning **the "current salary" used for payroll could non-deterministically be the wrong one**, with no error raised.

**Concurrency gaps:** the (currently nonexistent) "give employee a raise" operation would need to atomically set the old `EmployeeSalary.is_current=false` and insert a new current row; without a DB constraint, two concurrent raises (e.g., a manual correction racing an approved `SalaryChange` document) can both succeed, leaving two current rows.

**Production readiness: Schema-only.**

---

## Payroll

**Current implementation:** `PayrollPeriod` (year/month/branch, `PayrollPeriodStatus`: draft/calculating/calculated/approved/paid/locked, with `canCalculate()`/`canApprove()`/`canEdit()` helper methods on the enum — these are the most sophisticated state-machine helpers in the entire codebase, but they are **never called from anywhere** since no `PayrollService` exists to call them). `PayrollRecord` (per-employee line item with ~20 numeric columns).

**Business rules missing:** literally the entire calculation pipeline described in the preliminary audit (Attendance → Leave → Overtime → Salary → Allowances → Deductions → Insurance → Tax → Loans → Net). No code opens a period, no code calculates it, no code locks it. `PayrollPeriodStatus`'s well-designed transition helpers (`canCalculate()`, `canApprove()`, `canEdit()`) are dead code today.

**Data integrity gaps:** `payroll_records` has `unique(['payroll_period_id', 'employee_id'])` (`...000026...php:56`) — **correctly DB-enforced**, a real strength that prevents duplicate line items per employee per period. `payroll_periods` has `unique(['branch_id', 'year', 'month'])` (`...000025...php:31`) — also correctly enforced. `PayrollRecord.status` has **no enum cast** (confirmed: casts array in `src/Models/PayrollRecord.php:21-31` has no `status` entry despite `PayrollPeriodStatus` existing and being a plausible fit) — another stringly-typed-state inconsistency.

**Concurrency/idempotency gaps:** N/A today in the sense that there's no calculation code to race — but this is the single most important idempotency requirement to design correctly *before* writing the calculation engine: "calculate period" must be safe to re-run (recalculation) without double-counting or leaving stale records from a previous, different calculation run.

**Production readiness: Schema-only.** This is the domain the preliminary audit correctly flagged as most critical, and full inspection confirms there is genuinely zero calculation code — not "a simple version that needs hardening", but nothing.

---

## Insurance

**Current implementation:** **No model, no table, no service.** Only `config('hr.insurance.*')` exists (`config/hr.php:171-184`): social-security employee/employer/unemployment rates and a ceiling multiplier, plus a disabled-by-default "supplementary" insurance block.

**Business rules missing:** everything. There is no versioned insurance-rate entity (rates are hardcoded into a single config file with no effective-date/history), no ceiling calculation (`ceiling_multiplier: 7` — of what base? unspecified), no per-employee insurance-exemption flag, no link from `SalaryItem.is_insurable` (which *does* exist, `...000020...php:24`) to any actual computation.

**Regulatory flag:** the rates themselves (`employee_rate: 7`, `employer_rate: 20`, `unemployment_rate: 3`) look plausible for Iranian social security (تامین اجتماعی) as of recent years, but **this audit explicitly does not and cannot verify current legal correctness** — these are exactly the kind of values that change by government decree and must be verified against the current official rate table before any payroll run is trusted. Flag as **NEEDS VERIFICATION (legal/regulatory)**, not "implemented correctly".

**Production readiness: Not started** (config values only, no domain code, no versioning of those values over time).

---

## Tax

**Current implementation:** **No model, no table, no service.** Only `config('hr.tax.*')` (`config/hr.php:191-201`): `annual_exemption: 672000000`, 5 progressive brackets up to 30%.

**Business rules missing:** everything — no monthly-vs-annual reconciliation logic (Iranian salary tax is nominally annual/progressive but withheld monthly, which requires either annualizing monthly income or maintaining a running year-to-date figure — neither exists), no per-employee exemption handling (dependents, etc. — `Employee.dependents_count` exists as a column but is never read by anything tax-related since nothing is tax-related yet), no bracket-application function at all.

**Regulatory flag:** same caveat as Insurance — `annual_exemption: 672000000` and the bracket thresholds are plausible order-of-magnitude for a recent Iranian tax year, but **must be verified against the current official figures**; these numbers have no version/year tag in the config, so there is no way to tell which fiscal year they were written for, nor any mechanism to keep multiple years' brackets around for retroactive/historical payroll recalculation. Flag as **NEEDS VERIFICATION (legal/regulatory)**.

**Production readiness: Not started.**

---

## Loans

**Current implementation:** `Loan` (amount, installments, `installment_amount`, `remaining_amount`, `remaining_installments`, `LoanStatus` enum), `LoanPayment` (per-installment schedule, links optionally to a `payroll_record_id`). **No `LoanService` exists.**

**Business rules missing:** every config rule in `config('hr.loan.*')` (`config/hr.php:208-216`) — `max_amount`, `max_installments: 24`, `min_installments: 1`, `min_months_between_loans: 6`, `max_active_loans: 2`, `max_percentage_of_salary: 50` — is defined and enforced nowhere. Eligibility/approval has no code (only the `DocumentType::Loan` vocabulary exists). No early-repayment logic. No logic that actually decrements `remaining_amount`/`remaining_installments` when a `LoanPayment` is marked paid — these two loan-level fields and the payment-level rows can drift out of sync with nothing keeping them consistent.

**Data integrity gaps:** `loan_number` is globally unique (`...000023...php:17`, correctly enforced) — a genuine strength. `loan_payments` has **no unique constraint on `(loan_id, installment_number)`** (`...000024...php`) — duplicate installment-number rows for the same loan are fully representable, which would corrupt any future "remaining balance" calculation derived from summing payments.

**Concurrency gaps:** "check `max_active_loans`, then create a new loan" is a textbook check-then-insert race once eligibility logic is written — needs a lock on the employee's loan set or a DB-level constraint (e.g., a generated/maintained active-loan counter) rather than a plain `count()` check at request time.

**Integration points missing:** Loan → Payroll (loan installments should feed `PayrollRecord.loan_deduction`; `LoanPayment.payroll_record_id` exists as a column suggesting this was intended, but nothing populates it), Loan → Accounting (a disbursed loan is a receivable from the employee — see Accounting Integration section).

**Production readiness: Schema-only.**

---

## Documents (HrDocument)

**Current implementation:** `HrDocument` (typed, numbered, status-tracked container for HR actions), `DocumentAttachment`, `DocumentHistory` (audit trail), `DocumentService::create()/submit()/approve()/reject()`.

**Business logic present (real, working, for the Draft/Pending/Approved/Rejected happy path with a `user`-type single-step workflow):** creation defaults, `document_number` auto-generation (racy — see below), edit-lock via `DocumentStatus::canEdit()`, submit → create approvals, approve/reject → advance or reject the document, auto-lock after approval with a configurable delay, and a genuinely useful `DocumentHistory` audit trail on submit/approve/reject.

**Business rule gaps:**
- **Numbering concurrency bug** (Section 2, item 2) — reproducible scenario: two `hire` documents are created for two different employees in the same second; both execute `static::where('type','hire')->whereYear('created_at', 2026)->lockForUpdate()->count()` inside their own `DB::transaction()` closure that starts and **commits before the actual row exists** (the `count()` happens on the *pre-insert* table state, and the `INSERT` itself happens after the closure returns, in Eloquent's normal `save()` flow, entirely outside that transaction). Both closures can compute `count()+1 = 5`, so both documents get `document_number = 'HIR-2026-0005'`; since `document_number` is DB-unique (`...000015...php:18`), the second `INSERT` throws, and the entire `submit()`/creation call fails for the second caller with an unhandled `QueryException` rather than transparently retrying with the next number.
- `canEdit()`/`canDelete()`/`canSubmit()` all collapse to "only in Draft" (`src/Enums/DocumentStatus.php:35-48`) — reasonable defaults, but there's no override mechanism for, e.g., a `Rejected` document being edited and resubmitted (currently a rejected document is a dead end; the integrator must create a brand-new `HrDocument` from scratch).
- No enforcement that `HrDocument.employee_id`'s `branch_id` matches `HrDocument.branch_id` — these are independently settable (`branch_id` defaults to `$employee->branch_id` only in `DocumentService::create()`, `src/Services/DocumentService.php:44`, but nothing stops a caller from passing a different one via direct `HrDocument::create()`).

**Data integrity gaps:** `document_number` unique — correctly enforced at the DB layer (good), but as shown above the generation race means the *application* can still fail non-gracefully under concurrency. `hr_document_id`-style back-references from `LeaveRequest`, `MissionRequest`, `OvertimeRecord`, `EmployeePosition`, `EmployeeSalary`, `Loan`, `PayrollRecord` are all plain `unsignedBigInteger` columns with **no foreign key constraint** — every one of these can point at a non-existent or soft-deleted `HrDocument` with no DB-level protection (this appears to be a deliberate choice to avoid circular FK dependencies at migration time, since `HrDocument` is created *after* those tables in most cases — but the trade-off is undocumented and unenforced by the app layer either).

**Security gaps:** `advanceDocumentStatusIfComplete()` sets `approved_by => auth()->id()` (`src/Services/DocumentService.php:174`) and `recordHistory()` sets `user_id => auth()->id()` (`src/Services/DocumentService.php:206`) — both call the global `auth()` helper directly rather than accepting an actor parameter. This means: (a) calling `approve()`/`reject()` from a queued job, scheduled command, or CLI context silently records `null` as the actor with no error, and (b) there is **no verification that the currently authenticated user is the one assigned to the approval being acted on** — see Security & Authorization file for the full IDOR analysis.

**Concurrency gaps:** `approve()`/`reject()` read-then-write the `DocumentApproval` row (`if ($approval->status !== Pending) throw` then `$approval->update(...)`) with no `lockForUpdate()` — two near-simultaneous approve calls on the same approval (e.g., a double-click, or two admins acting on the same row) could both pass the pending check before either writes, though the visible symptom would likely just be a duplicate `DocumentHistory` entry rather than data corruption, since the final `status` write is idempotent-ish (both would set `Approved`). More seriously, `advanceDocumentStatusIfComplete()`'s `pendingCount`/`rejectedCount` counts are read without locking the document row, so two approvals for the same document approved at nearly the same instant race on which one calls `advanceDocumentStatusIfComplete()` last-but-one and gets a stale "pending count" — low risk in practice given the counts are recomputed fresh each call, but not lock-protected.

**Transaction gaps:** `submit()` does 1-3 writes (document status update, N approval inserts, history insert) with **no wrapping transaction** — a failure after the document status update but before all approvals are created leaves the document `Pending` with a partial/zero set of approvals, which is exactly the same broken state as the "no workflow found" bug, just triggered by a different failure mode (e.g., a DB timeout mid-loop).

**Test coverage:** none.

**Production readiness: Partial.** This is the most complete domain in the package — but "most complete" still means: one crash bug (workflow approver resolution), one concurrency bug (numbering), one missing transaction boundary, and zero authorization checks.

---

## Workflow

**Current implementation:** `Workflow` (per-`document_type`, optional `branch_id` scope, `priority`, `conditions` JSON, `findForDocument()` static resolver picking highest-priority active workflow matching type+branch), `WorkflowStep` (ordered, `approver_type`, `approver_id`, `condition` JSON, `is_required`, `can_reject`, `timeout_hours`, `timeout_action`), `DocumentApproval` (the actual per-step tracking row).

**What actually happens today, precisely:** `submit()` fetches the matching `Workflow` (correctly picks highest `priority`, correctly falls back to branch-null workflows), then **for every step, unconditionally**: creates a `DocumentApproval` with `assigned_to = $step->approver_id`, `status = Pending`, `deadline_at = now()->addHours($step->timeout_hours)` if set. That's it.

**What's advertised by the schema but never implemented:**
- `Workflow.conditions` and `WorkflowStep.condition` (both JSON) are **never read anywhere** — confirmed via search of `src/` for any `->conditions` or `->condition` property access outside the model class definitions themselves. A workflow/step "condition" (e.g., "only if `salary_change` amount exceeds X") is pure decoration today.
- `WorkflowStep.approver_type` (`ApproverType::User|Position|DepartmentHead|Custom`) is **never read**. Only `approver_id` is used, unconditionally, regardless of what `approver_type` says. This is the crash bug detailed in Sections 1–2: for `Position`/`DepartmentHead`/`Custom` steps, `approver_id` is normally left null (since the actual approver should be *resolved* at submit-time from the employee's department/position, not hardcoded on the workflow step), and `document_approvals.assigned_to` is `NOT NULL`.
- `WorkflowStep.is_required` is **never read** — `advanceDocumentStatusIfComplete()` only checks "any approval still pending?" (`src/Services/DocumentService.php:160-164`), treating every step as equally blocking regardless of `is_required`.
- `WorkflowStep.can_reject` is **never read** — any assigned approver can call `reject()` regardless of whether their step is flagged as allowed to reject.
- `WorkflowStep.timeout_hours`/`timeout_action` — `deadline_at` is stored but nothing ever reads it later (no scheduled command/job checks for overdue approvals and applies `timeout_action`, e.g., auto-approve or escalate). This is a fully inert field.
- `order` — steps are fetched in `order` via the `steps()` relation's `orderBy('order')` (`src/Models/Workflow.php:27`), but the **loop that creates approvals doesn't use that ordering for anything except insertion order**; every `DocumentApproval` is created as `Pending` in the same pass, so step 2's approver can act before step 1's approver does. There is no gating that makes this a *sequential* workflow; it behaves as **fully parallel** today regardless of intent.
- **No escalation, no auto-actions, no cancellation propagation**: rejecting one step (`reject()`) immediately sets the whole document to `Rejected` (`src/Services/DocumentService.php:144`) but leaves any *other* still-`Pending` `DocumentApproval` rows for that document exactly as they were — they are never marked `Skipped` (an enum case that exists, `ApprovalStatus::Skipped`, `src/Enums/ApprovalStatus.php:10`, but is **never assigned by any code path** — confirmed dead enum case). An approver whose step wasn't the one that rejected can still call `approve()` on their now-meaningless pending approval, since `approve()` never checks the parent document's current status before acting.

**Race conditions:** two steps' approvers both call `approve()`/`reject()` concurrently on *different* approval rows for the *same* document — both read "pending count > 0" before either write commits (in the reject case, both might think the document is still progressing), risking either a lost rejection signal or a document that ends up `Approved` after a `reject()` already fired (order-dependent on which write lands last, since `advanceDocumentStatusIfComplete()` and `reject()`'s direct `$document->update(['status' => Rejected])` are two independent, unsynchronized writers to the same `documents.status` column).

**Production readiness: Not started, as an engine.** As an "approval assignment recorder" (the preliminary audit's characterization) it functions only for the single happy path: one active workflow, every step `approver_type = user` with an explicit non-null `approver_id`, no conditions, no required/optional distinction needed, no timeouts, no rejections-with-other-pending-steps. Any deviation from that path either silently misbehaves or crashes.

---

## Accounting Integration (boundary only — no implementation reviewed/recommended here)

**Current state:** the package has **zero code that touches accounting** — there is no `AccountingIntegration` contract, no event dispatched on payroll approval, no interface for "post a journal entry". This is good news from a coupling standpoint (no dangerous coupling exists today because nothing exists), but it also means the boundary is entirely undesigned.

**HR events with financial consequences (should eventually notify/produce accounting documents, via an event or a contract the HR package defines and the consumer implements):**
- Payroll period **approval** (gross payroll expense, employer insurance contribution, tax withheld as a liability, net salary payable as a liability) — the single highest-value integration point.
- Payroll period **payment** (settling the salary-payable liability against a cash/bank account).
- **Loan disbursement** (an employee receivable) and **loan repayment via payroll deduction** (reducing that receivable, recognized at each payroll run).
- **Employer insurance contribution** (a distinct expense/liability from the employee's own withheld contribution).

**Events that should NOT directly depend on accounting** (HR domain logic must not import/call an accounting package):
- Employee CRUD, leave requests, attendance, contract lifecycle, organization structure — none of these have a direct financial posting requirement; they are inputs to payroll, not accounting events themselves.
- Document workflow generally — approving a `leave` or `position_change` document has no accounting consequence by itself.

**What contracts/interfaces are needed (minimal, not over-engineered):** a single narrow interface the HR package defines (e.g., something like a `PayrollPostedListener`/event payload contract carrying period id, per-employee gross/net/insurance/tax/loan-deduction totals) that an accounting package can implement/subscribe to. This should be an **event**, not a synchronous method call into an accounting service — HR must remain usable standalone (installable and functional with zero accounting package present).

**How salary-payable / employer-insurance should be represented:** as data already present in `PayrollRecord` (net_salary, payable, insurance_employer, tax) — the *representation* is fine; what's missing is (a) the calculation that populates it correctly and (b) an event fired when a period is approved/paid that an accounting consumer can listen to, carrying that data.

**Dangerous coupling today:** none found — but that is only because there is no payroll engine yet. The concrete risk to flag now, before the payroll engine is built: it would be easy (and wrong) to have the future `PayrollService` directly call into `karnoweb/laravel-accounting` (or similar) to post journal entries synchronously. That must not happen; the boundary should be an event/contract HR defines, with accounting posting handled entirely outside this package.
