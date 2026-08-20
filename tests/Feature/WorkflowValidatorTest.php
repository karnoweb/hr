<?php

namespace Karnoweb\Hr\Tests\Feature;

use InvalidArgumentException;
use Karnoweb\Hr\Enums\ApproverType;
use Karnoweb\Hr\Enums\DocumentType;
use Karnoweb\Hr\Models\Workflow;
use Karnoweb\Hr\Models\WorkflowStep;
use Karnoweb\Hr\Services\ConditionEvaluator;
use Karnoweb\Hr\Tests\TestCase;

class WorkflowValidatorTest extends TestCase
{
    public function test_malformed_workflow_conditions_fail_closed_at_validation(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Workflow::query()->create([
            'branch_id' => 1,
            'name' => 'Invalid conditions',
            'document_type' => DocumentType::Leave->value,
            'is_active' => true,
            'priority' => 1,
            'conditions' => ['field' => 'days'],
        ]);
    }

    public function test_malformed_step_condition_operator_rejected(): void
    {
        $workflow = Workflow::query()->create([
            'branch_id' => 1,
            'name' => 'Invalid step condition',
            'document_type' => DocumentType::Leave->value,
            'is_active' => true,
            'priority' => 1,
        ]);

        $this->expectException(InvalidArgumentException::class);

        WorkflowStep::query()->create([
            'workflow_id' => $workflow->id,
            'order' => 1,
            'name' => 'Bad operator',
            'approver_type' => ApproverType::User,
            'approver_id' => 9001,
            'condition' => ['field' => 'days', 'operator' => 'contains', 'value' => 3],
        ]);
    }

    public function test_in_operator_requires_array_value(): void
    {
        $evaluator = $this->app->make(ConditionEvaluator::class);

        $this->expectException(InvalidArgumentException::class);
        $evaluator->validateConditions([
            ['field' => 'type', 'operator' => 'in', 'value' => 'annual'],
        ]);
    }
}
