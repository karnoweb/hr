<?php

namespace Karnoweb\Hr\Exceptions;

/**
 * Thrown when an organizational structure operation is invalid: department cycle,
 * soft-deleting a department that still has children, etc.
 */
class InvalidOrganizationStructureException extends HrException {}
