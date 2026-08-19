# Implementation Checklist — Phase 13: Security / Performance / Hardening

Part of the Karnoweb HR audit. See `HR-AUDIT.md` for the index. Consolidates cross-cutting hardening once Phases 1–12 have landed.

- [ ] HR-149 — Document branch-scoping as an explicit, required integration responsibility across every branch-scoped model (P1)
  - Area: `docs/USAGE.md`, class docblocks
  - Acceptance:
    - a dedicated "Multi-branch integration checklist" section lists every branch-scoped model and states plainly that the host app must apply its own scoping (global scopes/policies)
    - cross-references the audit's Security & Authorization findings

- [ ] HR-150 — Add IDOR regression tests for HR-120's authorization check, plus explicit tests documenting where no check exists by design (P1)
  - Area: new `tests/Security/`
  - Acceptance:
    - a dedicated test group states, for every service method, whether an authorization check exists in-package or is explicitly deferred to the caller — turning the audit's Section 8 findings into permanent, living, tested documentation

- [ ] HR-151 — Add `laravel/pint` and `larastan/larastan` as direct dev dependencies (currently only transitive) (P2)
  - Area: `composer.json`
  - Acceptance: cross-reference Phase 0's HR-003/HR-004; tracked here for hardening-phase completeness

- [ ] HR-152 — Run a full Pint pass and commit the resulting formatting baseline (P2)
  - Area: entire `src/`, `database/`, `tests/`
  - Acceptance:
    - `vendor/bin/pint --test` passes cleanly after this item
    - establishes a clean baseline for CI (HR-003) to enforce going forward

- [ ] HR-153 — Run a full Larastan pass and commit a baseline file for pre-existing issues (P2)
  - Area: entire `src/`
  - Acceptance:
    - `vendor/bin/phpstan analyse --generate-baseline` produces a baseline capturing today's issues
    - CI enforces "no new issues beyond baseline" from this point forward

- [ ] HR-154 — Measure and, only if justified, optimize `WorkingDayCalculator`'s repeated Holiday/config lookups at realistic payroll-period scale (P3, contingent on measurement)
  - Area: `src/Support/WorkingDayCalculator.php`
  - Acceptance:
    - a profiling test (added as part of this item) is run first; optimization work is only performed if that test actually shows a measurable cost at realistic scale (hundreds of employees per period), per the "do not prematurely optimize" instruction

- [ ] HR-155 — Add eager-loading/batching for payroll-period-wide calculation queries (P2)
  - Area: `PayrollService`/`PayrollCalculator`
  - Acceptance:
    - a batch-mode calculation path (all employees in a period) uses eager-loaded/batched queries rather than N per-employee round trips per sub-domain
    - verified via a query-count assertion in a Feature test (query count does not scale linearly with employee count beyond a small constant factor)

- [ ] HR-156 — Verify `Department::updatePath()`'s transaction wrapper (cross-reference HR-026) (P2)
  - Area: cross-reference to HR-026
  - Acceptance: verified covered by Phase 2's HR-026

- [ ] HR-157 — Harden `ApproverResolver`/`ConditionEvaluator` to fail closed on malformed input (P1)
  - Area: `ApproverResolver`, `ConditionEvaluator` (Phase 11)
  - Acceptance:
    - malformed condition/approver configuration fails closed (rejected at validation time, per HR-136) rather than failing open (silently resolving to no approver, which combined with `skip_on_no_approver=true` could bypass approval entirely)
    - tested explicitly for the fail-closed behavior

- [ ] HR-158 — Document (not implement) rate-limiting/anti-spam guidance for leave/loan/mission request creation (P3)
  - Area: `docs/USAGE.md`
  - Acceptance:
    - documents that the package itself does not rate-limit request creation and that the host app should apply its own throttling if abuse is a concern — deliberately documentation-only, per the "do not over-engineer" instruction

- [ ] HR-159 — Consolidated index/FK completeness review across every migration added in Phases 1–12 (P1)
  - Area: all new migrations
  - Acceptance:
    - a single review pass confirms every new foreign-key-shaped column has a supporting index and every new unique-invariant column has the intended constraint, before the v14 release

- [ ] HR-160 — Write the Security & Performance hardening test suite (P1)
  - Area: `tests/Security/`, `tests/Performance/`
  - Acceptance:
    - consolidates HR-150's authorization-documentation tests and HR-155's query-count assertions into a clearly labeled, CI-run test group
