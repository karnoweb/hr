<?php

namespace Karnoweb\Hr\Support;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Iranian national ID (کد ملی) checksum helper and optional Laravel validation rule.
 *
 * Opt-in only — EmployeeService does not enforce this automatically so integrators
 * with non-Iranian employees are not blocked.
 */
class IranianNationalId implements ValidationRule
{
    public static function isValid(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        if (! preg_match('/^\d{10}$/', $value)) {
            return false;
        }

        // Reject trivial repeating patterns (0000000000, 1111111111, …).
        if (preg_match('/^(\d)\1{9}$/', $value)) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += (int) $value[$i] * (10 - $i);
        }

        $remainder = $sum % 11;
        $checkDigit = (int) $value[9];

        return $remainder < 2
            ? $checkDigit === $remainder
            : $checkDigit === 11 - $remainder;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::isValid($value)) {
            $fail('The :attribute is not a valid Iranian national ID.');
        }
    }
}
