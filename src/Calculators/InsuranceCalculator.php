<?php

namespace Karnoweb\Hr\Calculators;

use Carbon\Carbon;
use InvalidArgumentException;
use Karnoweb\Hr\Models\InsuranceRate;

/**
 * Social insurance contributions (HR-104 / HR-108).
 *
 * Ceiling base = versioned `insurance_rates.minimum_wage` (config fallback)
 * × rate.ceiling_multiplier. Rates are looked up by payroll date.
 */
class InsuranceCalculator
{
    /**
     * @return array{
     *     insurable_base: float,
     *     capped_base: float,
     *     insurance_employee: float,
     *     insurance_employer: float,
     *     insurance_unemployment: float,
     *     insurance_rate_id: int|null,
     *     minimum_wage: float,
     *     ceiling_multiplier: float,
     *     rule_effective_date: string|null
     * }
     */
    public function calculate(float $insurableBase, Carbon $asOfDate, bool $insuranceExempt = false): array
    {
        if ($insuranceExempt || ! config('hr.insurance.social_security.enabled', true)) {
            return [
                'insurable_base' => round($insurableBase, 2),
                'capped_base' => 0.0,
                'insurance_employee' => 0.0,
                'insurance_employer' => 0.0,
                'insurance_unemployment' => 0.0,
                'insurance_rate_id' => null,
                'minimum_wage' => 0.0,
                'ceiling_multiplier' => 0.0,
                'rule_effective_date' => null,
            ];
        }

        $rate = InsuranceRate::forDate($asOfDate);

        if ($rate === null) {
            throw new InvalidArgumentException('No insurance rate configured for the payroll date.');
        }

        $minimumWage = $rate->minimum_wage !== null
            ? (float) $rate->minimum_wage
            : (float) config('hr.payroll.minimum_wage', 0);
        $ceiling = round($minimumWage * (float) $rate->ceiling_multiplier, 2);
        $cappedBase = round(min(max(0, $insurableBase), $ceiling), 2);

        return [
            'insurable_base' => round($insurableBase, 2),
            'capped_base' => $cappedBase,
            'insurance_employee' => round($cappedBase * ((float) $rate->employee_rate / 100), 2),
            'insurance_employer' => round($cappedBase * ((float) $rate->employer_rate / 100), 2),
            'insurance_unemployment' => round($cappedBase * ((float) $rate->unemployment_rate / 100), 2),
            'insurance_rate_id' => $rate->id,
            'minimum_wage' => $minimumWage,
            'ceiling_multiplier' => (float) $rate->ceiling_multiplier,
            'rule_effective_date' => $rate->effective_date?->toDateString(),
        ];
    }
}
