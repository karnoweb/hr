<?php

namespace Karnoweb\Hr\Calculators;

use Carbon\Carbon;
use InvalidArgumentException;
use Karnoweb\Hr\Models\InsuranceRate;

/**
 * Social insurance contributions (HR-104 / HR-108).
 *
 * Ceiling base = config('hr.payroll.minimum_wage') × rate.ceiling_multiplier.
 * Rates are looked up from versioned `insurance_rates` rows by payroll date.
 */
class InsuranceCalculator
{
    /**
     * @return array{
     *     insurable_base: float,
     *     capped_base: float,
     *     insurance_employee: float,
     *     insurance_employer: float,
     *     insurance_unemployment: float
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
            ];
        }

        $rate = InsuranceRate::forDate($asOfDate);

        if ($rate === null) {
            throw new InvalidArgumentException('No insurance rate configured for the payroll date.');
        }

        $minimumWage = (float) config('hr.payroll.minimum_wage', 0);
        $ceiling = round($minimumWage * (float) $rate->ceiling_multiplier, 2);
        $cappedBase = round(min(max(0, $insurableBase), $ceiling), 2);

        return [
            'insurable_base' => round($insurableBase, 2),
            'capped_base' => $cappedBase,
            'insurance_employee' => round($cappedBase * ((float) $rate->employee_rate / 100), 2),
            'insurance_employer' => round($cappedBase * ((float) $rate->employer_rate / 100), 2),
            'insurance_unemployment' => round($cappedBase * ((float) $rate->unemployment_rate / 100), 2),
        ];
    }
}
