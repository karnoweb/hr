# Implementation Checklist — Phase 0: Foundation & Safety

Part of the Karnoweb HR audit. See `HR-AUDIT.md` for the index. This phase must land before any domain-specific phase, since Phases 1+ reuse the sequence generator, the "current record" pattern, the exception hierarchy, and the test/CI infrastructure established here.

- [x] HR-001 — Create a working test-suite skeleton (Orchestra Testbench + PHPUnit) (P0)
  - Area: `composer.json`, new `tests/TestCase.php`, new `phpunit.xml`
  - Acceptance:
    - `tests/TestCase.php` extends `Orchestra\Testbench\TestCase` and registers `HrServiceProvider`
    - `phpunit.xml` (or `phpunit.xml.dist`) exists and is valid
    - migrations load and run cleanly against an in-memory/SQLite test DB
    - `vendor/bin/phpunit` runs successfully with zero tests (proves the harness works) before any real test is added

- [x] HR-002 — Add a GitHub Actions CI workflow running the test suite on PHP 8.3 / Laravel 13 (P1)
  - Area: `.github/workflows/tests.yml`
  - Acceptance:
    - triggers on push and pull_request
    - runs `composer install` then `vendor/bin/phpunit`
    - fails the build on any test failure
    - matrix covers at least PHP 8.3 with Laravel 13 (extend later if the `^1.0` Laravel 10–12 line needs its own CI job)

- [x] HR-003 — Add Laravel Pint as a direct dev dependency with a committed `pint.json` and a CI style-check step (P2)
  - Area: `composer.json`, new `pint.json`, CI workflow
  - Acceptance:
    - `laravel/pint` is a direct (not merely transitive) dev dependency
    - `vendor/bin/pint --test` either passes on the current codebase or a documented baseline commit brings it to a passing state
    - CI fails the build on any future style violation

- [x] HR-004 — Add Larastan/PHPStan as a direct dev dependency with a committed baseline config (P2)
  - Area: `composer.json`, new `phpstan.neon` (or `.dist`)
  - Acceptance:
    - `larastan/larastan` is a direct dev dependency
    - `vendor/bin/phpstan analyse` runs at an explicitly chosen level
    - a baseline file captures pre-existing issues so CI enforces "no new issues" from day one, without requiring a full retroactive cleanup before this item can land

- [x] HR-005 — Create an `hr_sequences` table + `SequenceGenerator` service for atomic, reusable numeric sequence allocation (P0)
  - Area: new migration, new `src/Support/SequenceGenerator.php` (namespace/location at implementer's discretion, but must be reusable, not duplicated)
  - Acceptance:
    - `nextValue(string $scope): int` is atomic under `lockForUpdate()` inside a `DB::transaction()`
    - a concurrency test firing N parallel calls for the same `$scope` returns N distinct, gapless integer values
    - this service is the one and only mechanism Phase 1 (employee code) and Phase 10 (document number) use going forward — no bespoke per-domain sequence logic remains after those phases land

- [x] HR-006 — Define and document the reusable "exactly one current record" DB pattern (nullable `current_key` + unique index) (P0)
  - Area: a short ADR/doc comment (e.g. in `docs/ARCHITECTURE.md` or a code comment on the first migration that uses it); actual migrations are written per-domain in Phases 2 and 6
  - Acceptance:
    - the pattern (a nullable column set to the owning row's natural key only when "current", with a unique index on that column) is written down once, with the exact SQL shape, so Phases 2/6 apply it identically rather than inventing three different ad-hoc solutions
    - explicitly notes this is the MySQL-compatible approach (multiple `NULL`s are not considered duplicates by a unique index), since the package doesn't assume a specific RDBMS elsewhere

- [x] HR-007 — Bind `Hr` sub-services in the service container instead of manual `new`-ing (P1)
  - Area: `src/HrServiceProvider.php`, `src/Hr.php`
  - Acceptance:
    - `EmployeeService`, `LeaveService`, `DocumentService` (and every service added in later phases) are resolved via `$this->app->make(...)` / container `singleton`/`scoped` bindings registered in `HrServiceProvider::register()`
    - `Hr::employees()`/`leave()`/`documents()` delegate to the container instead of `new X`
    - existing public method signatures on `Hr` are unchanged (no breaking change from this item alone)

- [x] HR-008 — Introduce a small hierarchy of domain exceptions extending a common `HrException` base (P1)
  - Area: `src/Exceptions/`
  - Acceptance:
    - at minimum: `DuplicateActiveRecordException`, `UnresolvableApproverException`, `InsufficientLeaveBalanceException`, `PayrollPeriodLockedException`, `UnauthorizedApprovalException` exist and extend a common base
    - each is actually thrown from a specific, documented call site introduced in a later phase (this item itself only creates the classes; wiring happens per-phase)
    - follows the existing `DocumentLockedException` naming/placement convention

- [x] HR-009 — Add a `LICENSE` file matching `composer.json`'s declared MIT license (P2)
  - Area: repository root
  - Acceptance:
    - `LICENSE` file present, standard MIT text, correct copyright holder/year
    - matches `composer.json`'s `"license": "MIT"` declaration

- [x] HR-010 — Add an "Unreleased" breaking-change tracking section to `CHANGELOG.md` (P2)
  - Area: `CHANGELOG.md`
  - Acceptance:
    - a `## [Unreleased]` section exists with `### Added` / `### Changed` / `### BREAKING` subheadings
    - ready to receive one entry per checklist item as Phases 1-13 land, so the eventual v14.0.0 release notes aren't reconstructed from scratch at the end
