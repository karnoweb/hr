# Contributing to Karnoweb HR

Thank you for contributing. This package is a **Laravel domain library** (not a full application): business rules live in services, tests run via Orchestra Testbench, and releases align with Laravel major versions.

## Local setup

```bash
composer install
```

Requirements: PHP 8.3+, Composer 2.x.

## Quality checks (run before every PR)

```bash
composer test                    # PHPUnit (202+ tests)
composer pint                    # Laravel Pint (format)
composer analyse                 # PHPStan / Larastan
vendor/bin/pint --test           # CI-style format check (no writes)
```

All three must pass. GitHub Actions (`.github/workflows/tests.yml`) runs the same steps on push/PR to `main`, `master`, and `develop`.

## Versioning

- **Major versions track Laravel:** `karnoweb/hr` **v13.x** targets **Laravel 13**; the legacy **v1.x** line targets Laravel 10–12.
- **v14.0.0** is the first audit-complete release after Phases 0–13 (substantial domain implementation vs. early v13 schema-only state). Treat upgrades as requiring migration review and integration testing — see [CHANGELOG.md](CHANGELOG.md).
- Git tags (`v13.0.6`, `v14.0.0`, …) are the source of truth for releases.
- `composer.json` includes a `"version"` field for Packagist visibility; **update it in the same commit as each release tag** (see [HR-169](HR-AUDIT-PHASE-14.md)).

## Release process (maintainers)

1. Ensure `CHANGELOG.md` has a dated section (move items out of `[Unreleased]`).
2. Bump `composer.json` `"version"` to match the tag.
3. Run `composer test`, `vendor/bin/pint --test`, `vendor/bin/phpstan analyse`.
4. Commit: `Release X.Y.Z: short summary.`
5. Tag: `git tag vX.Y.Z`
6. Push branch and tags; publish GitHub release notes summarizing user-facing changes.

## Where to put new code

See [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) for Service vs Calculator vs Event vs Exception boundaries.

## Repository documentation map

| Path | Purpose |
|------|---------|
| [README.md](README.md) | Package overview and quick start |
| [docs/USAGE.md](docs/USAGE.md) | Detailed usage (Persian), facade examples |
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | ADRs and layer boundaries |
| [docs/ACCOUNTING.md](docs/ACCOUNTING.md) | Accounting integration events (no accounting package dependency) |
| [hr-package.md](hr-package.md) | Original design blueprint (historical) |
| [HR-AUDIT.md](HR-AUDIT.md) | Audit index |
| [HR-AUDIT-PHASE-*.md](HR-AUDIT-PHASE-00.md) | Phase implementation checklists (living history) |
| [HR-AUDIT-FINAL-SUMMARY.md](HR-AUDIT-FINAL-SUMMARY.md) | Release gate / Definition of Done |
| [PHASE-STATUS.md](PHASE-STATUS.md) | High-level phase tracker |

Audit and blueprint files at the repo root are **historical record** — keep them for traceability; do not delete when updating user-facing docs.

## Test layout

| Directory | Role |
|-----------|------|
| `tests/Feature/` | Service happy paths and domain rules |
| `tests/Unit/` | Pure helpers, enums, calculators |
| `tests/Security/` | Authorization documentation matrix |
| `tests/Performance/` | Query-count / scaling guards |
| `tests/Architecture/` | Package boundary guards (e.g. no accounting dependency) |
| `tests/Integration/` | Standalone usage scenarios |

## Branch protection

Enable required status checks for the **Tests** workflow on `main` in GitHub repository settings (team policy if settings are out of scope for this repo).
