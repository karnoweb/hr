<?php

namespace Karnoweb\Hr\Exceptions;

/**
 * Thrown when creating an Employee for an employable that already has one,
 * or when a uniqueness constraint on employee identity is violated.
 *
 * Wired in Phase 1 (EmployeeService / employable uniqueness).
 */
class EmployeeAlreadyExistsException extends HrException {}
