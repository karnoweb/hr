<?php

namespace Karnoweb\Hr\Facades;

use Illuminate\Support\Facades\Facade;
use Karnoweb\Hr\Services\DocumentService;
use Karnoweb\Hr\Services\EmployeeService;
use Karnoweb\Hr\Services\LeaveService;

/**
 * Facade for the HR (Human Resources) package.
 *
 * Provides static access to HR configuration and services: employees, leave, and documents.
 * Use this facade for IDE autocomplete and type-safe access to HR functionality.
 *
 *
 * @method static mixed config(string $key, mixed $default = null) Get an HR config value by key.
 * @method static \Karnoweb\Hr\Services\EmployeeService employees() Get the employee service (create/find, lifecycle, codes).
 * @method static \Karnoweb\Hr\Services\ContractService contracts() Get the contract service (hire, renew, extend, terminate).
 * @method static \Karnoweb\Hr\Services\AttendanceService attendance() Get the attendance service (clock in/out, corrections).
 * @method static \Karnoweb\Hr\Services\ShiftAssignmentService shiftAssignments() Assign fixed shifts or rotating patterns.
 * @method static \Karnoweb\Hr\Services\LeaveService leave() Get the leave service (request leave, check balance).
 * @method static \Karnoweb\Hr\Services\MissionService missions() Get the mission service (business trips).
 * @method static \Karnoweb\Hr\Services\OvertimeService overtime() Get the overtime service (sync, approve, caps).
 * @method static \Karnoweb\Hr\Services\SalaryService salaries() Get the salary service (assign, change, calculate).
 * @method static \Karnoweb\Hr\Services\DocumentService documents() Get the document service (create, submit, approve, reject HR documents).
 *
 * @mixin \Karnoweb\Hr\Hr
 *
 * @see \Karnoweb\Hr\Hr
 * @see EmployeeService
 * @see LeaveService
 * @see DocumentService
 */
class Hr extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'hr';
    }
}
