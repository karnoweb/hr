<?php

namespace Karnoweb\Hr\Exceptions;

/**
 * Thrown when an actor attempts to approve or reject a DocumentApproval
 * that is assigned to a different user.
 *
 * Wired in Phase 10 (DocumentService authorization check).
 */
class UnauthorizedApprovalException extends HrException {}
