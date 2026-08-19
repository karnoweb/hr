<?php

namespace Karnoweb\Hr\Exceptions;

use Exception;

/**
 * Base exception for all Karnoweb HR domain errors.
 *
 * Domain-specific exceptions should extend this class so callers can
 * catch `HrException` for any package-level failure, or a concrete
 * subtype for a specific failure mode.
 */
class HrException extends Exception {}
