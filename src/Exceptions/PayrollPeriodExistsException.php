<?php

namespace Karnoweb\Hr\Exceptions;

/**
 * Thrown when opening a payroll period that already exists for branch/year/month.
 */
class PayrollPeriodExistsException extends HrException {}
