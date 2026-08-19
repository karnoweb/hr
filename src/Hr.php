<?php

namespace Karnoweb\Hr;

use Illuminate\Contracts\Foundation\Application;
use Karnoweb\Hr\Services\AttendanceService;
use Karnoweb\Hr\Services\ContractService;
use Karnoweb\Hr\Services\DocumentService;
use Karnoweb\Hr\Services\EmployeeService;
use Karnoweb\Hr\Services\LeaveService;
use Karnoweb\Hr\Services\MissionService;
use Karnoweb\Hr\Services\OvertimeService;
use Karnoweb\Hr\Services\SalaryService;
use Karnoweb\Hr\Services\ShiftAssignmentService;

class Hr
{
    public function __construct(
        protected Application $app,
    ) {}

    public function config(string $key, mixed $default = null): mixed
    {
        return config('hr.'.$key, $default);
    }

    public function employees(): EmployeeService
    {
        return $this->app->make(EmployeeService::class);
    }

    public function contracts(): ContractService
    {
        return $this->app->make(ContractService::class);
    }

    public function attendance(): AttendanceService
    {
        return $this->app->make(AttendanceService::class);
    }

    public function shiftAssignments(): ShiftAssignmentService
    {
        return $this->app->make(ShiftAssignmentService::class);
    }

    public function leave(): LeaveService
    {
        return $this->app->make(LeaveService::class);
    }

    public function missions(): MissionService
    {
        return $this->app->make(MissionService::class);
    }

    public function overtime(): OvertimeService
    {
        return $this->app->make(OvertimeService::class);
    }

    public function salaries(): SalaryService
    {
        return $this->app->make(SalaryService::class);
    }

    public function documents(): DocumentService
    {
        return $this->app->make(DocumentService::class);
    }
}
