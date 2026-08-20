<?php

namespace Karnoweb\Hr\Services;

use InvalidArgumentException;
use Karnoweb\Hr\Models\HrDocument;

/**
 * Minimal workflow condition evaluation against HrDocument.data (HR-127).
 *
 * Supported shape (single rule or list of rules — all must match):
 * {"field":"days","operator":"gte","value":3}
 */
class ConditionEvaluator
{
    /**
     * @param  array<string, mixed>|list<array<string, mixed>>|null  $conditions
     */
    public function matches(?array $conditions, HrDocument $document): bool
    {
        if ($conditions === null || $conditions === []) {
            return true;
        }

        if ($this->isRule($conditions)) {
            return $this->evaluateRule($conditions, $document);
        }

        foreach ($conditions as $rule) {
            if (! is_array($rule) || ! $this->isRule($rule)) {
                throw new InvalidArgumentException('Invalid workflow condition schema.');
            }

            if (! $this->evaluateRule($rule, $document)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    protected function isRule(array $rule): bool
    {
        return array_key_exists('field', $rule) && array_key_exists('operator', $rule);
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    protected function evaluateRule(array $rule, HrDocument $document): bool
    {
        $field = (string) $rule['field'];
        $operator = (string) $rule['operator'];
        $expected = $rule['value'] ?? null;
        $actual = data_get($document->data ?? [], $field);

        return match ($operator) {
            'eq', '==' => $actual == $expected,
            'ne', '!=' => $actual != $expected,
            'gt' => $actual > $expected,
            'gte' => $actual >= $expected,
            'lt' => $actual < $expected,
            'lte' => $actual <= $expected,
            'in' => is_array($expected) && in_array($actual, $expected, true),
            'not_in' => is_array($expected) && ! in_array($actual, $expected, true),
            default => throw new InvalidArgumentException("Unsupported condition operator [{$operator}]."),
        };
    }
}
