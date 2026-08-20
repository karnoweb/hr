# Implementation status

Small tracker for the HR-AUDIT phase work. Update as each phase lands.

| Phase | Status | Notes |
|-------|--------|--------|
| 0 Foundation & Safety | **Done** | Test harness, CI, Pint, PHPStan baseline, SequenceGenerator, current-record ADR, container bindings, exception hierarchy, LICENSE, CHANGELOG Unreleased |
| 1 Core Employee Domain | **Done** | Sequence codes, unique identity indexes, terminate/reactivate/suspend, national-ID helper, lifecycle guard, BaseModel double-prefix fix, docs Security model |
| 2 Organization & Contracts | **Done** | Branch-scoped codes, department cycle/delete guards, transactional updatePath, ContractService, active_key/current_key invariants, assignPosition hardening, HrDocumentReference, 62 tests |
| 3 Attendance + Shifts | **Done** | AttendanceService, ShiftResolver, ShiftAssignmentService, WorkingDayCalculator, auto clock-out command, shift assignment current_key, holiday/weekend placeholders, 83 tests |
| 4 Leave + Mission | **Done** | LeaveBalanceService, LeaveService lifecycle, MissionService, carry-over command, attendance marking, termination balance policy, 99 tests |
| 5 Overtime | **Done** | OvertimeService, OvertimeMinuteClassifier, OvertimeStatus enum, attendance sync, min_minutes/monthly_cap/pre-approval, Hr::overtime(), 111 tests |
| 6 Salary | **Done** | current_key migration, SalaryService, SalaryCalculator, expression evaluator, item validation, Hr::salaries(), 128 tests |
| 7 Loans | **Done** | LoanService, LoanPaymentStatus, installment unique, limits/schedule/repayment, Hr::loans(), 138 tests |
| 8 Payroll | **Done** | PayrollService, PayrollCalculator, Insurance/Tax calculators, golden-master test, Hr::payroll(), 148 tests |
| 9 Insurance + Tax | **Done** | Versioned rates, calculators, import command, employee exemptions, dependents config |
| 10 Documents | **Done** | Concurrency-safe numbering, transactional submit/approve/reject, authorization, resubmit |
| 11 Workflow | **Done** | ApproverResolver, WorkflowEngine, conditions, sequential/parallel, timeouts, cancel |
| 12 Accounting boundary | **Done** | PayrollPeriodApproved/Paid + LoanDisbursed events, docs, standalone test |
| 13 Security / Performance | Not started | |
| 14 Docs / Release / CI | Not started | |
