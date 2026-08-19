<?php

namespace Karnoweb\Hr\Exceptions;

/**
 * Thrown when a document is submitted and no matching Workflow can be found,
 * and config('hr.workflow.skip_on_no_approver') is false.
 *
 * Wired in Phase 10 (DocumentService::submit).
 */
class UnresolvableWorkflowException extends HrException {}
