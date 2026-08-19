<?php

namespace Karnoweb\Hr\Exceptions;

/**
 * Thrown when a mutating payroll operation is attempted against a period
 * that is no longer editable (Approved / Paid / Locked).
 *
 * Wired in Phase 8 (PayrollService).
 */
class PayrollPeriodLockedException extends HrException {}
