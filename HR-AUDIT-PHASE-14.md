# Implementation Checklist — Phase 14: Documentation / Release / CI

Part of the Karnoweb HR audit. See `HR-AUDIT.md` for the index. Final phase before tagging v14.0.0.

- [x] HR-161 — Verify `LICENSE` file is present (HR-009)
  - `LICENSE` (MIT) at repository root

- [x] HR-162 — Rewrite `README.md` to accurately reflect implemented-vs-planned functionality (P1)
  - Implemented feature table, roadmap for out-of-scope items, doc index

- [x] HR-163 — Update `docs/USAGE.md` to qualify/remove raw `Model::create()` bypasses (P1)
  - Service-first examples for employee, position, documents, shifts; master-data notes for dept/position/shift/salary items

- [x] HR-164 — Clarify role of blueprint and audit files (P3)
  - `CONTRIBUTING.md` documentation map; links from `README.md`

- [x] HR-165 — Populate `CHANGELOG.md` as phases land (P2)
  - Sections through 13.0.6 + 14.0.0 release notes

- [x] HR-166 — Add `CONTRIBUTING.md` (P2)
  - Local test/pint/phpstan workflow, versioning, release process

- [x] HR-167 — Finalize CI (tests + Pint + Larastan) (P1)
  - `.github/workflows/tests.yml` — branch protection documented in `CONTRIBUTING.md`

- [x] HR-168 — Tag and document v14.0.0 release notes (P1)
  - `CHANGELOG.md` [14.0.0]; git tag `v14.0.0`

- [x] HR-169 — `composer.json` version field (P3)
  - Kept and documented: update in lockstep with each release tag

- [x] HR-170 — Expand `docs/ARCHITECTURE.md` (P2)
  - Service / Calculator / Event / Exception layer table

- [x] HR-171 — Confirm test coverage meets Definition of Done (P0)
  - 202 tests green; P0 audit items covered by Feature/Security/Architecture suites

- [x] HR-172 — Final review: changes trace to checklist IDs (P1)
  - Phases 0–14 tracked in `HR-AUDIT-PHASE-*.md` and `PHASE-STATUS.md`
