<?php

namespace Karnoweb\Hr\Exceptions;

/**
 * Thrown when an operation would create a second "current"/"active"
 * record for an entity that must have exactly one (e.g. current salary,
 * primary position, or active contract).
 *
 * Wired in later phases (Contracts, Employee Position, Salary).
 */
class DuplicateActiveRecordException extends HrException {}
