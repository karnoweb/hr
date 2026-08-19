<?php

namespace Karnoweb\Hr\Exceptions;

/**
 * Thrown when a leave request exceeds the employee's remaining leave balance.
 *
 * Wired in Phase 4 (LeaveService balance validation).
 */
class InsufficientLeaveBalanceException extends HrException {}
