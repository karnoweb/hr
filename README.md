# Karnoweb HR

A comprehensive HR management package for Laravel with Iranian (Jalali) calendar support, payroll, leave, attendance, workflow, and document management.

**راهنمای استفاده (فارسی):** [docs/USAGE.md](docs/USAGE.md)

## Requirements

- PHP 8.3+
- Laravel 13.x
- morilog/jalali ^3.0

## Installation

```bash
# Laravel 13
composer require karnoweb/hr:^13.0

# Laravel 10–12
composer require karnoweb/hr:^1.0
```

Publish config (optional):

```bash
php artisan vendor:publish --tag=hr-config
```

Run migrations:

```bash
php artisan migrate
```

## Configuration

Edit `config/hr.php` (or use env) for:

- **Models**: `HR_USER_MODEL`, `HR_BRANCH_MODEL`
- **Calendar**: `HR_CALENDAR_TYPE` (jalali | gregorian), week/year start
- **Leave types**: annual, sick, unpaid, hourly, marriage, maternity, bereavement
- **Overtime**: rates (regular, holiday, night), night window, monthly cap
- **Insurance**: social security rates (employee 7%, employer 20%, unemployment 3%)
- **Tax**: brackets and annual exemption (1403 defaults)
- **Loan**: max installments, min months between loans, max % of salary
- **Payroll**: closing day, minimum wage, payment day
- **Workflow**: document types requiring approval, auto-lock

## Usage

Common operations are done via the **`Hr` facade** and its sub-services; in your app you usually need a single `use`.

### Facade and sub-services

```php
use Karnoweb\Hr\Facades\Hr;

// Config
Hr::config('leave.types.annual.days_per_year');

// Employees: create for User, find by User, assign position (employee_code auto-generated if empty)
$employee = Hr::employees()->createForUser($user, ['branch_id' => 1, 'hire_date' => now()]);
$employee = Hr::employees()->findByUser($user);
Hr::employees()->assignPosition($employee, $departmentId, $positionId, $effectiveDate);
Hr::employees()->generateEmployeeCode($branchId);

// Leave: request leave, get balance
$request = Hr::leave()->request($employee, ['type' => 'annual', 'start_date' => $from, 'end_date' => $to, 'days' => 3]);
$balance = Hr::leave()->balance($employee, 1403, 'annual');

// Documents and workflow: create, submit, approve/reject step
$doc = Hr::documents()->create(DocumentType::Leave, $employee, ['leave_request_id' => $request->id, 'days' => 3], ['created_by' => auth()->id()]);
Hr::documents()->submit($doc);
Hr::documents()->approve($approval, $comment);
Hr::documents()->reject($approval, $comment);
```

### Models and Enums (advanced)

For direct database access use:

- **Models** (`Karnoweb\Hr\Models`): Employee, Department, Position, EmployeePosition, Contract, LeaveRequest, LeaveBalance, HrDocument, Workflow, WorkflowStep, DocumentApproval, SalaryItem, SalaryStructure, EmployeeSalary, Loan, PayrollPeriod, PayrollRecord, …
- **Enums** (`Karnoweb\Hr\Enums`): EmployeeStatus, ContractType, DocumentType, DocumentStatus, LeaveRequestStatus, ApprovalStatus, …

More examples: [docs/USAGE.md](docs/USAGE.md)

## Table prefix

All tables use the `hr_` prefix by default (config: `hr.tables.prefix`).

## License

MIT.
