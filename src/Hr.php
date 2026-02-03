<?php

namespace Karnoweb\Hr;

use Karnoweb\Hr\Services\DocumentService;
use Karnoweb\Hr\Services\EmployeeService;
use Karnoweb\Hr\Services\LeaveService;

class Hr
{
    protected ?EmployeeService $employeeService = null;

    protected ?LeaveService $leaveService = null;

    protected ?DocumentService $documentService = null;

    public function config(string $key, mixed $default = null): mixed
    {
        return config('hr.' . $key, $default);
    }

    public function employees(): EmployeeService
    {
        if ($this->employeeService === null) {
            $this->employeeService = new EmployeeService;
        }

        return $this->employeeService;
    }

    public function leave(): LeaveService
    {
        if ($this->leaveService === null) {
            $this->leaveService = new LeaveService;
        }

        return $this->leaveService;
    }

    public function documents(): DocumentService
    {
        if ($this->documentService === null) {
            $this->documentService = new DocumentService;
        }

        return $this->documentService;
    }
}
