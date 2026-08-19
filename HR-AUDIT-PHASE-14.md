# Implementation Checklist — Phase 14: Documentation / Release / CI

Part of the Karnoweb HR audit. See `HR-AUDIT.md` for the index. Final phase before tagging v14.0.0.

- [ ] HR-161 — Verify `LICENSE` file is present (cross-reference HR-009) (P2)
  - Area: cross-reference to Phase 0's HR-009
  - Acceptance: verified covered by HR-009

- [ ] HR-162 — Rewrite `README.md` to accurately reflect implemented-vs-planned functionality (P1)
  - Area: `README.md`
  - Acceptance:
    - either reflects only what's actually shipped at release time, or clearly marks aspirational/roadmap items as such (e.g. a "Roadmap" section), rather than presenting everything as already functional as it does today

- [ ] HR-163 — Update `docs/USAGE.md` to remove/qualify raw `Model::create()` examples for every domain that now has a real service (P1)
  - Area: `docs/USAGE.md`
  - Acceptance:
    - each domain's usage example calls its service method (enforcing the business rules built in Phases 1–11) rather than bypassing them via direct Eloquent writes

- [ ] HR-164 — Clarify the role of `hr-package.md` and the audit files (`review.md`, `HR-AUDIT*.md`) from the repository's documentation entry points (P3)
  - Area: `README.md` or a new `docs/` index
  - Acceptance:
    - a new contributor has a clear signal about what these files are (original design blueprint; audit history) and whether they remain relevant, rather than finding them unreferenced at the repo root
    - these files should generally be kept as historical record, not deleted, but linked from a `CONTRIBUTING.md` or docs index so their purpose is unambiguous

- [ ] HR-165 — Populate `CHANGELOG.md`'s "Unreleased" section as each phase lands (P2)
  - Area: `CHANGELOG.md`
  - Acceptance:
    - cross-reference Phase 0's HR-010
    - by the time v14.0.0 ships, every breaking change from Phases 1–13 has a corresponding, specific entry (not a generic "various fixes")

- [ ] HR-166 — Add a `CONTRIBUTING.md` documenting the release process, versioning convention, and local test/analysis workflow (P2)
  - Area: new `CONTRIBUTING.md`
  - Acceptance:
    - a new contributor can run the full test + style + static-analysis suite locally by following this document alone
    - clarifies that major-version numbers currently track Laravel-version alignment (per the Public API Audit's finding), to avoid confusing "v13"/"v14" with semantic-versioning-only meaning

- [ ] HR-167 — Finalize CI to run tests + Pint + Larastan on every PR and require it to pass before merge (P1)
  - Area: `.github/workflows/`
  - Acceptance:
    - depends on Phase 0's HR-002/003/004
    - branch protection (or a documented team policy, if branch protection is out of scope for this repo's settings) requires this workflow to pass

- [ ] HR-168 — Tag and document the v14.0.0 release notes summarizing the full scope of Phases 0–13 (P1)
  - Area: `CHANGELOG.md`, GitHub release notes
  - Acceptance:
    - release notes clearly communicate that v14 is a substantial, deliberately breaking upgrade from v13's data-model-only state, per the Public API Audit's backward-compatibility findings

- [ ] HR-169 — Decide the fate of `composer.json`'s hardcoded `version` field (P3)
  - Area: `composer.json`
  - Acceptance:
    - either the field is removed (letting Composer derive the version from the VCS tag, the more conventional approach) or a documented process ensures it's updated in lockstep with every tag going forward

- [ ] HR-170 — Add a minimal `docs/ARCHITECTURE.md` documenting the Domain/Services/Calculators/Exceptions/Events split adopted across Phases 0–12 (P2)
  - Area: new `docs/ARCHITECTURE.md`
  - Acceptance:
    - explains where new business logic should live (Service vs. Calculator vs. Exception vs. Event) using the concrete criteria from the Architecture Recommendations "Improve" section, restated in the contributor's own words, so this shape doesn't need to be re-derived by every future contributor

- [ ] HR-171 — Confirm test coverage meets the Definition of Done before tagging v14.0.0 (P0)
  - Area: entire `tests/` suite
  - Acceptance:
    - every P0 item across Phases 0–13 has at least one corresponding automated test that was failing before the fix and passes after (a true regression test, not merely a happy-path addition)
    - see `HR-AUDIT-FINAL-SUMMARY.md`'s Definition of Done section for the full release gate

- [ ] HR-172 — Final full-repository review pass confirming every change traces back to a specific checklist item (P1)
  - Area: entire repository
  - Acceptance:
    - a diff review against the pre-Phase-0 state confirms every change traces back to a specific checklist item ID, supporting clean, reviewable, per-phase pull requests
