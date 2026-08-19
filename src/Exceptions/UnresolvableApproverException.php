<?php

namespace Karnoweb\Hr\Exceptions;

/**
 * Thrown when a workflow step's approver cannot be resolved to a concrete
 * user id before creating a DocumentApproval row (e.g. department_head /
 * position / custom steps with missing org data).
 *
 * Wired in Phase 11 (Workflow / ApproverResolver).
 */
class UnresolvableApproverException extends HrException {}
