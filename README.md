# Karnoweb HR

A comprehensive HR management package for Laravel with Iranian (Jalali) calendar support, payroll, leave, attendance, workflow, and document management.

## Requirements

- PHP 8.2+
- Laravel 10.x, 11.x or 12.x
- morilog/jalali ^3.0

## Installation

```bash
composer require karnoweb/hr
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

### Models (namespace `Karnoweb\Hr\Models`)

- **Employee** – morph to your User (employable); employee_code, hire_date, status, branch
- **Department** – tree (parent_id, path, level)
- **Position**, **EmployeePosition** – current position with effective_date history
- **Contract** – type (permanent, temporary, …), status (active, ended, terminated)
- **Shift**, **ShiftPattern**, **EmployeeShiftAssignment**
- **Holiday**, **AttendanceRecord**, **OvertimeRecord**
- **LeaveRequest**, **MissionRequest**, **LeaveBalance**
- **HrDocument** – type (hire, termination, position_change, salary_change, leave, mission, loan, …), status (draft, pending, approved, rejected, locked), workflow
- **Workflow**, **WorkflowStep**, **DocumentApproval**, **DocumentHistory**
- **SalaryItem**, **SalaryStructure**, **EmployeeSalary**, **EmployeeSalaryItem**
- **Loan**, **LoanPayment**
- **PayrollPeriod**, **PayrollRecord**

### Enums (namespace `Karnoweb\Hr\Enums`)

EmployeeStatus, ContractType, ContractStatus, DocumentType, DocumentStatus, AttendanceStatus, LeaveRequestStatus, OvertimeType, PayrollPeriodStatus, SalaryItemType, CalculationType, LoanStatus, ApproverType, ApprovalStatus.

### Facade

```php
use Karnoweb\Hr\Facades\Hr;

Hr::config('leave.types.annual.days_per_year');
```

## Table prefix

All tables use the `hr_` prefix by default (config: `hr.tables.prefix`).

## License

MIT.
