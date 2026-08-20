# Karnoweb HR

A Laravel **domain package** for Iranian HR: employees, organization, contracts, attendance, leave, overtime, salary, loans, payroll (insurance/tax), documents, workflow, and accounting-boundary events.

**مستندات:** [docs/README.md](docs/README.md) — [مفاهیم](docs/concepts/README.md) و [طرز استفاده](docs/usage/README.md)  
**Contributing / release:** [CONTRIBUTING.md](CONTRIBUTING.md)

## Requirements

- PHP 8.3+
- Laravel 13.x
- morilog/jalali ^3.0

## Installation

```bash
# Laravel 13
composer require karnoweb/hr:^13.2

# Laravel 10–12 (legacy line)
composer require karnoweb/hr:^1.0
```

```bash
php artisan vendor:publish --tag=hr-config   # optional
php artisan migrate
```

## What is implemented (v13.2)

Use the **`Hr` facade** and sub-services for business operations — they enforce invariants (single current salary/contract/position, document locking, workflow approval, payroll lifecycle, etc.).

| Domain | Facade accessor | Notes |
|--------|-----------------|-------|
| Employees | `Hr::employees()` | create, lifecycle, position assignment |
| Contracts | `Hr::contracts()` | hire, renew, extend, terminate |
| Attendance | `Hr::attendance()` | clock in/out, metrics |
| Shift assignment | `Hr::shiftAssignments()` | fixed shift or pattern |
| Leave | `Hr::leave()` | request, approve, balance |
| Missions | `Hr::missions()` | request, approve |
| Overtime | `Hr::overtime()` | sync from attendance, approve |
| Salary | `Hr::salaries()` | assign, changeSalary |
| Loans | `Hr::loans()` | apply, approve, repay, payroll deductions |
| Payroll | `Hr::payroll()` | open period, calculate, approve, mark paid |
| Documents | `Hr::documents()` | create, submit, approve/reject, cancel |
| Accounting | Events only | `PayrollPeriodApproved`, `PayrollPeriodPaid`, `LoanDisbursed` — see [docs/concepts/accounting.md](docs/concepts/accounting.md) |

**Not included:** HTTP controllers, Filament resources, authorization policies, branch global scopes, or rate limiting — the host app must add these (see [docs/usage/security.md](docs/usage/security.md)).

## Quick example

```php
use Karnoweb\Hr\Facades\Hr;
use Karnoweb\Hr\Enums\DocumentType;

$employee = Hr::employees()->createForUser($user, ['branch_id' => 1]);
Hr::salaries()->assign($employee, ['base_salary' => 50_000_000, 'effective_date' => '2026-01-01']);

$leave = Hr::leave()->request($employee, [
    'type' => 'annual', 'start_date' => '2026-05-01', 'end_date' => '2026-05-03', 'days' => 3,
]);

$doc = Hr::documents()->create(DocumentType::Leave, $employee, ['leave_request_id' => $leave->id]);
Hr::documents()->submit($doc, actorId: auth()->id());
```

More: [docs/usage/README.md](docs/usage/README.md)

## Configuration

Key areas in `config/hr.php`: calendar, leave types, overtime rates, insurance/tax, loans, payroll, documents, workflow, accounting event dispatch.

## Documentation index

| Document | Description |
|----------|-------------|
| [docs/README.md](docs/README.md) | Documentation index |
| [docs/concepts/](docs/concepts/README.md) | Domain concepts + future-labeled gaps |
| [docs/usage/](docs/usage/README.md) | Facade usage, rules, errors, stored results |
| [CHANGELOG.md](CHANGELOG.md) | Release history |
| [hr-package.md](hr-package.md) | Original design blueprint (historical) |
| [HR-AUDIT.md](HR-AUDIT.md) | Implementation audit index |

## Roadmap (out of package scope)

- Admin UI (Filament/API controllers)
- Host-app policies and branch scoping middleware
- Legal rate verification for production payroll (**NEEDS VERIFICATION** flags in config/seeds)

## Development

```bash
composer test && vendor/bin/pint --test && vendor/bin/phpstan analyse --memory-limit=1G
```

See [CONTRIBUTING.md](CONTRIBUTING.md).

## Table prefix

Default `hr_` — `config('hr.tables.prefix')`.

## License

MIT — see [LICENSE](LICENSE).
