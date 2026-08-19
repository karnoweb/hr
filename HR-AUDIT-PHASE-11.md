# Implementation Checklist — Phase 11: Workflow

Part of the Karnoweb HR audit. See `HR-AUDIT.md` for the index. Depends on Phase 10 (Documents fixes) and Phase 0.

- [ ] HR-125 — Design and implement `ApproverResolver`: one resolution method per `ApproverType` (P0)
  - Area: new `src/Services/ApproverResolver.php`
  - Acceptance:
    - `User` type returns `approver_id` directly
    - `DepartmentHead` resolves the document's employee's current department's head — requires explicitly defining "department head" (likely a small, documented schema addition, e.g. `Department.head_employee_id`, since no such concept exists today)
    - `Position` resolves the current holder(s) of a specified position
    - `Custom` resolves via a documented, pluggable callback/condition
    - every branch is unit-tested
    - unresolvable cases throw `UnresolvableApproverException` (Phase 0, HR-008) **before** any `DocumentApproval` row is attempted, closing the NOT-NULL-constraint crash bug at its root cause

- [ ] HR-126 — Integrate `ApproverResolver` into `DocumentService::submit()`, replacing the direct `approver_id` copy (P0)
  - Area: `DocumentService::submit()`
  - Acceptance:
    - `submit()` resolves every step's approver via `ApproverResolver` before writing anything (resolution happens as a pure read pass, before/outside the HR-114 transaction)
    - the previously-guaranteed crash for non-`user` steps no longer occurs
    - the HR-124/HR-137 regression test turns green

- [ ] HR-127 — Evaluate `WorkflowStep.condition`/`Workflow.conditions` JSON before activating a step (P0)
  - Area: new `src/Services/ConditionEvaluator.php` (small and restricted — not a general rules engine)
  - Acceptance:
    - a step whose condition doesn't match the document's data is automatically marked `Skipped` rather than `Pending` at submit time
    - the supported condition schema/operators are explicitly documented and deliberately minimal (e.g. simple key/operator/value comparisons against `HrDocument.data`, not an arbitrary expression language)

- [ ] HR-128 — Respect `WorkflowStep.is_required` in the document-advancement logic (P0)
  - Area: `DocumentService`/`WorkflowEngine`
  - Acceptance:
    - an optional (`is_required = false`) step's `Pending` status no longer blocks document completion
    - only required steps' pending/rejected state determines advancement
    - tested with a mix of required and optional steps

- [ ] HR-129 — Respect `WorkflowStep.can_reject` (P1)
  - Area: `DocumentService::reject()`/`WorkflowEngine`
  - Acceptance:
    - calling `reject()` on an approval whose step has `can_reject = false` throws a clear exception
    - tested

- [ ] HR-130 — Add explicit sequential-vs-parallel execution semantics (P0)
  - Area: new migration adding `Workflow.execution_mode` (enum: sequential/parallel), new `WorkflowEngine`
  - Acceptance:
    - for `sequential` workflows, step N+1's approvals only activate once step N's required approvals are resolved
    - for `parallel` (today's only actual behavior), all steps activate at submit time as they do now
    - both modes tested
    - the default value for existing/legacy workflows is explicitly `parallel`, to avoid a silent behavior change for anything already configured

- [ ] HR-131 — Implement `timeout_hours`/`timeout_action` processing via a scheduled command (P0)
  - Area: new `src/Console/Commands/ProcessWorkflowTimeoutsCommand.php`
  - Acceptance:
    - finds `DocumentApproval` rows past their `deadline_at` and applies the corresponding step's `timeout_action`
    - the finite set of supported actions (e.g. `auto_approve`, `auto_reject`, `escalate`) is documented and each is fully implemented (no TODO placeholders)
    - registered on the scheduler
    - tested with a time-traveled (`Carbon::setTestNow`) approval past its deadline

- [ ] HR-132 — Implement escalation-target resolution for the `escalate` timeout action (P1)
  - Area: `ApproverResolver` + `ProcessWorkflowTimeoutsCommand`
  - Acceptance:
    - escalation reassigns `assigned_to` to a documented, resolvable escalation target (e.g. the approver's manager, or a configured fallback) rather than silently doing nothing
    - tested

- [ ] HR-133 — Implement workflow/document cancellation before any step acts (P1)
  - Area: new `DocumentService::cancel()`
  - Acceptance:
    - transitions the document to a cancellable state (may require a new `Cancelled` `DocumentStatus` case, or reuse of `Rejected` with a distinguishing history entry — decision documented)
    - marks all `Pending` approvals `Skipped`, atomically

- [ ] HR-134 — Verify the reject()-vs-concurrent-approve() race is resolved (cross-reference HR-116) (P0)
  - Area: cross-reference to HR-116/117/118
  - Acceptance: verified covered by Phase 10's fixes

- [ ] HR-135 — Implement `auto_approve_own_department` policy (P2)
  - Area: `ApproverResolver`/`WorkflowEngine`
  - Acceptance:
    - when `config('hr.workflow.auto_approve_own_department')` is enabled, a step whose resolved approver is the document's own submitter/employee auto-approves that step immediately
    - disabled by default, matching the current config default
    - tested

- [ ] HR-136 — Add `Workflow`/`WorkflowStep` configuration validation at creation time (P1)
  - Area: model validation or a new `WorkflowValidator` service
  - Acceptance:
    - creating a `WorkflowStep` with an `approver_type` the resolver cannot resolve (e.g. `Position` with no usable position reference) is rejected at creation time, not discovered later at submit time

- [ ] HR-137 — Add the headline regression test: full submit → approve for a `Position`-type and a `DepartmentHead`-type step, end-to-end (P0)
  - Area: `tests/Feature/WorkflowTest.php`
  - Acceptance:
    - a full submit → both non-`user` step types resolve correctly → approve both → document reaches `Approved`, with no exception
    - this is the single most important regression test in the entire checklist: it directly proves the Section 1/2 headline crash bug (Section 1, item 1) is fixed

- [ ] HR-138 — Write the full Workflow domain test suite (P0)
  - Area: `tests/Feature/`, `tests/Unit/`
  - Acceptance:
    - covers HR-125 through HR-137: at least one test per `ApproverType`, one per condition-match/no-match, one per required/optional, one per sequential/parallel mode, one per `timeout_action`, one for escalation, one for cancellation
