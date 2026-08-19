<?php

namespace Karnoweb\Hr\Exceptions;

/**
 * Thrown when an employee lifecycle operation is invalid: wrong status transition,
 * hire_date / termination_date inconsistency, or a status change that bypasses
 * EmployeeService::terminate() / reactivate() / suspend().
 */
class InvalidEmployeeLifecycleException extends HrException {}
