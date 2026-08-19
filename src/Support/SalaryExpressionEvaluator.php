<?php

namespace Karnoweb\Hr\Support;

use InvalidArgumentException;

/**
 * Safe arithmetic expression evaluator for salary formulas (HR-073).
 *
 * Supports literals, + - * /, parentheses, {@code {ITEM_CODE}} references,
 * and the reserved identifier {@code base_salary}. Does not use eval().
 */
class SalaryExpressionEvaluator
{
    /** @var list<string> */
    private array $tokens = [];

    private int $position = 0;

    /** @var array<string, float> */
    private array $variables = [];

    /**
     * Validate expression syntax and referenced identifiers.
     *
     * @param  list<string>  $allowedIdentifiers  e.g. item codes plus "base_salary"
     */
    public function validate(string $expression, array $allowedIdentifiers): void
    {
        $this->assertSafeCharacters($expression);

        foreach ($this->extractIdentifiers($expression) as $identifier) {
            if (! in_array($identifier, $allowedIdentifiers, true)) {
                throw new InvalidArgumentException("Formula references unknown identifier [{$identifier}].");
            }
        }

        $this->evaluate($expression, array_fill_keys($allowedIdentifiers, 1.0));
    }

    /**
     * @param  array<string, float|int|string>  $variables
     */
    public function evaluate(string $expression, array $variables): float
    {
        $this->assertSafeCharacters($expression);
        $this->variables = array_map(static fn ($value) => (float) $value, $variables);
        $this->tokenize($expression);
        $this->position = 0;

        $result = $this->parseExpression();

        if ($this->position < count($this->tokens)) {
            throw new InvalidArgumentException('Unexpected token in salary formula.');
        }

        return round($result, 2);
    }

    /**
     * @return list<string>
     */
    public function extractIdentifiers(string $expression): array
    {
        preg_match_all('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', $expression, $braced);

        $identifiers = $braced[1];

        if (preg_match('/\bbase_salary\b/', $expression)) {
            $identifiers[] = 'base_salary';
        }

        return array_values(array_unique($identifiers));
    }

    private function assertSafeCharacters(string $expression): void
    {
        if ($expression === '') {
            throw new InvalidArgumentException('Salary formula cannot be empty.');
        }

        if (! preg_match('/^[\d\s+\-*\/().a-zA-Z_{}]+$/', $expression)) {
            throw new InvalidArgumentException('Salary formula contains unsupported characters.');
        }
    }

    private function tokenize(string $expression): void
    {
        $normalized = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', ' $1 ', $expression) ?? $expression;
        $normalized = preg_replace('/\bbase_salary\b/', ' base_salary ', $normalized) ?? $normalized;

        preg_match_all('/\d+\.\d+|\d+|[a-zA-Z_][a-zA-Z0-9_]*|[+\-*\/()]/', $normalized, $matches);

        $this->tokens = $matches[0];

        if ($this->tokens === []) {
            throw new InvalidArgumentException('Salary formula could not be parsed.');
        }
    }

    private function parseExpression(): float
    {
        $value = $this->parseTerm();

        while ($this->position < count($this->tokens)) {
            $operator = $this->tokens[$this->position];

            if ($operator !== '+' && $operator !== '-') {
                break;
            }

            $this->position++;
            $right = $this->parseTerm();
            $value = $operator === '+' ? $value + $right : $value - $right;
        }

        return $value;
    }

    private function parseTerm(): float
    {
        $value = $this->parseFactor();

        while ($this->position < count($this->tokens)) {
            $operator = $this->tokens[$this->position];

            if ($operator !== '*' && $operator !== '/') {
                break;
            }

            $this->position++;
            $right = $this->parseFactor();

            if ($operator === '/' && abs($right) < 1e-12) {
                throw new InvalidArgumentException('Division by zero in salary formula.');
            }

            $value = $operator === '*' ? $value * $right : $value / $right;
        }

        return $value;
    }

    private function parseFactor(): float
    {
        if ($this->position >= count($this->tokens)) {
            throw new InvalidArgumentException('Unexpected end of salary formula.');
        }

        $token = $this->tokens[$this->position];

        if ($token === '(') {
            $this->position++;
            $value = $this->parseExpression();

            if (($this->tokens[$this->position] ?? null) !== ')') {
                throw new InvalidArgumentException('Missing closing parenthesis in salary formula.');
            }

            $this->position++;

            return $value;
        }

        if ($token === '-') {
            $this->position++;

            return -$this->parseFactor();
        }

        if ($token === '+') {
            $this->position++;

            return $this->parseFactor();
        }

        if (is_numeric($token)) {
            $this->position++;

            return (float) $token;
        }

        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $token)) {
            $this->position++;

            if (! array_key_exists($token, $this->variables)) {
                throw new InvalidArgumentException("Formula references unknown identifier [{$token}].");
            }

            return $this->variables[$token];
        }

        throw new InvalidArgumentException("Unexpected token [{$token}] in salary formula.");
    }
}
