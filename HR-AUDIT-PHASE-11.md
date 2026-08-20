# Implementation Checklist — Phase 11: Workflow

Part of the Karnoweb HR audit. See `HR-AUDIT.md` for the index. Depends on Phase 10 (Documents fixes) and Phase 0.

- [x] HR-125 — Design and implement `ApproverResolver`: one resolution method per `ApproverType` (P0)
- [x] HR-126 — Integrate `ApproverResolver` into `DocumentService::submit()` via `WorkflowEngine` (P0)
- [x] HR-127 — Evaluate `WorkflowStep.condition`/`Workflow.conditions` JSON before activating a step (P0)
- [x] HR-128 — Respect `WorkflowStep.is_required` in the document-advancement logic (P0)
- [x] HR-129 — Respect `WorkflowStep.can_reject` (P1)
- [x] HR-130 — Add explicit sequential-vs-parallel execution semantics (P0)
- [x] HR-131 — Implement `timeout_hours`/`timeout_action` processing via `hr:process-workflow-timeouts` (P0)
- [x] HR-132 — Implement escalation-target resolution for the `escalate` timeout action (P1)
- [x] HR-133 — Implement workflow/document cancellation via `DocumentService::cancel()` (P1)
- [x] HR-134 — Verify the reject()-vs-concurrent-approve() race is resolved (cross-reference HR-116) (P0)
  - Covered by Phase 10 DocumentService locking; unchanged in Phase 11.
- [x] HR-135 — Implement `auto_approve_own_department` policy (P2)
- [x] HR-136 — Add `WorkflowStep` configuration validation at creation time (P1)
- [x] HR-137 — Headline regression test: Position + DepartmentHead submit → approve (P0)
- [x] HR-138 — Write the full Workflow domain test suite (P0)
