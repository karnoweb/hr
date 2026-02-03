<?php

namespace Karnoweb\Hr\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Facade for the HR (Human Resources) package.
 *
 * Provides static access to HR configuration and services: employees, leave, and documents.
 * Use this facade for IDE autocomplete and type-safe access to HR functionality.
 *
 * @package Karnoweb\Hr\Facades
 *
 * @method static mixed config(string $key, mixed $default = null) Get an HR config value by key.
 * @method static \Karnoweb\Hr\Services\EmployeeService employees() Get the employee service (create/find employees, assign positions, generate codes).
 * @method static \Karnoweb\Hr\Services\LeaveService leave() Get the leave service (request leave, check balance).
 * @method static \Karnoweb\Hr\Services\DocumentService documents() Get the document service (create, submit, approve, reject HR documents).
 *
 * @mixin \Karnoweb\Hr\Hr
 *
 * @see \Karnoweb\Hr\Hr
 * @see \Karnoweb\Hr\Services\EmployeeService
 * @see \Karnoweb\Hr\Services\LeaveService
 * @see \Karnoweb\Hr\Services\DocumentService
 */
class Hr extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'hr';
    }
}
