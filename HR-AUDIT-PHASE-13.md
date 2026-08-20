# Implementation Checklist — Phase 13: Security / Performance / Hardening

Part of the Karnoweb HR audit. See `HR-AUDIT.md` for the index. Consolidates cross-cutting hardening once Phases 1–12 have landed.

- [x] HR-149 — Document branch-scoping as an explicit, required integration responsibility across every branch-scoped model (P1)
  - `docs/USAGE.md` — section «امنیت، چند-شعبه‌ای، و مسئولیت یکپارچه‌سازی»

- [x] HR-150 — Add IDOR regression tests for HR-120's authorization check, plus explicit tests documenting where no check exists by design (P1)
  - `tests/Security/ServiceAuthorizationMatrixTest.php` (+ existing `DocumentTest` IDOR cases)

- [x] HR-151 — Add `laravel/pint` and `larastan/larastan` as direct dev dependencies (P2)
  - Already present in `composer.json` require-dev since Phase 0

- [x] HR-152 — Run a full Pint pass and commit the resulting formatting baseline (P2)
  - `vendor/bin/pint --test` passes

- [x] HR-153 — Run a full Larastan pass and commit a baseline file for pre-existing issues (P2)
  - Clean pass at level configured in `phpstan.neon` — no baseline file required

- [x] HR-154 — Measure and, only if justified, optimize `WorkingDayCalculator` (P3)
  - **Deferred:** no profiling test added; no measurable bottleneck identified at current scale — per «do not prematurely optimize»

- [x] HR-155 — Add eager-loading/batching for payroll-period-wide calculation queries (P2)
  - `PayrollBatchContext`, wired in `PayrollService::calculate()`; `tests/Performance/PayrollBatchQueryTest.php`

- [x] HR-156 — Verify `Department::updatePath()`'s transaction wrapper (HR-026) (P2)
  - Covered by Phase 2 `OrganizationContractTest` — no change required

- [x] HR-157 — Harden `ApproverResolver`/`ConditionEvaluator` to fail closed on malformed input (P1)
  - `ConditionEvaluator::validateConditions()`; `Workflow`/`WorkflowStep` saving hooks; `tests/Feature/WorkflowValidatorTest.php`

- [x] HR-158 — Document rate-limiting/anti-spam guidance (P3)
  - `docs/USAGE.md` — subsection «محدودیت نرخ درخواست‌ها»

- [x] HR-159 — Consolidated index/FK completeness review (P1)
  - Phase 11–12 migrations reviewed: `head_employee_id` has FK (+ implicit index); `execution_mode` is enum string on workflows; `escalation_user_id` intentionally has **no FK** (host-app user id, same pattern as `assigned_to` on approvals)

- [x] HR-160 — Write the Security & Performance hardening test suite (P1)
  - `tests/Security/`, `tests/Performance/`
