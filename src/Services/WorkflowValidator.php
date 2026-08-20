<?php

namespace Karnoweb\Hr\Services;

use InvalidArgumentException;
use Karnoweb\Hr\Models\Workflow;
use Karnoweb\Hr\Models\WorkflowStep;

/**
 * Validate workflow configuration at write time (HR-136).
 */
class WorkflowValidator
{
    public function __construct(
        protected ApproverResolver $approvers,
    ) {}

    public function validateWorkflow(Workflow $workflow): void
    {
        $workflow->loadMissing('steps');

        foreach ($workflow->steps as $step) {
            $this->validateStep($step);
        }
    }

    public function validateStep(WorkflowStep $step): void
    {
        if ($step->name === '') {
            throw new InvalidArgumentException('Workflow step name is required.');
        }

        $this->approvers->validateStepConfiguration($step);
    }
}
