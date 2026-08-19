<?php

namespace Karnoweb\Hr\Tests\Unit;

use InvalidArgumentException;
use Karnoweb\Hr\Support\SalaryExpressionEvaluator;
use Karnoweb\Hr\Tests\TestCase;

class SalaryExpressionEvaluatorTest extends TestCase
{
    public function test_evaluates_arithmetic_with_identifiers(): void
    {
        $evaluator = new SalaryExpressionEvaluator;

        $result = $evaluator->evaluate('{BASE} * 0.1 + base_salary', [
            'BASE' => 1_000_000,
            'base_salary' => 50_000_000,
        ]);

        $this->assertSame(50_100_000.0, $result);
    }

    public function test_rejects_unsafe_characters(): void
    {
        $evaluator = new SalaryExpressionEvaluator;

        $this->expectException(InvalidArgumentException::class);
        $evaluator->validate('system("rm")', ['base_salary']);
    }

    public function test_rejects_unknown_identifier(): void
    {
        $evaluator = new SalaryExpressionEvaluator;

        $this->expectException(InvalidArgumentException::class);
        $evaluator->validate('{UNKNOWN} + 1', ['base_salary']);
    }
}
