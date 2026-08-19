<?php

namespace Karnoweb\Hr\Calculators;

use Carbon\Carbon;
use InvalidArgumentException;
use Karnoweb\Hr\Models\TaxBracket;

/**
 * Progressive income tax (HR-106 / HR-108 / HR-109 / HR-110).
 *
 * Policy: **monthly annualization** — monthly taxable income × 12 is run through
 * annual brackets and exemption, then the resulting annual tax ÷ 12 is withheld
 * this month. Year-to-date reconciliation is deferred (NEEDS VERIFICATION).
 */
class TaxCalculator
{
    /**
     * @return array{
     *     taxable_income: float,
     *     tax: float,
     *     annualized_taxable: float,
     *     annual_exemption_applied: float
     * }
     */
    public function calculateMonthly(
        float $monthlyTaxable,
        Carbon $asOfDate,
        int $dependentsCount = 0,
        float $additionalAnnualExemption = 0,
        bool $taxExempt = false,
    ): array {
        $monthlyTaxable = max(0, $monthlyTaxable);
        $annualTaxable = $monthlyTaxable * 12;

        if ($taxExempt || ! config('hr.tax.enabled', true)) {
            return [
                'taxable_income' => round($monthlyTaxable, 2),
                'tax' => 0.0,
                'annualized_taxable' => round($annualTaxable, 2),
                'annual_exemption_applied' => 0.0,
            ];
        }

        $bracketSet = TaxBracket::forDate($asOfDate);

        if ($bracketSet === null) {
            throw new InvalidArgumentException('No tax bracket configuration for the payroll date.');
        }

        $annualExemption = (float) $bracketSet->annual_exemption
            + max(0, $additionalAnnualExemption)
            + $this->dependentsExemption($dependentsCount);

        $taxableAfterExemption = max(0, $annualTaxable - $annualExemption);
        $annualTax = $this->applyBrackets($taxableAfterExemption, $bracketSet->brackets);

        return [
            'taxable_income' => round($monthlyTaxable, 2),
            'tax' => round($annualTax / 12, 2),
            'annualized_taxable' => round($annualTaxable, 2),
            'annual_exemption_applied' => round($annualExemption, 2),
        ];
    }

    protected function dependentsExemption(int $dependentsCount): float
    {
        if ($dependentsCount <= 0 || ! config('hr.tax.dependents_exemption.enabled', false)) {
            return 0;
        }

        return $dependentsCount * (float) config('hr.tax.dependents_exemption.per_dependent_annual', 0);
    }

    /**
     * @param  array<int, array{up_to: int|float|null, rate: int|float}>  $brackets
     */
    protected function applyBrackets(float $taxableAnnual, array $brackets): float
    {
        $remaining = $taxableAnnual;
        $previousCap = 0.0;
        $tax = 0.0;

        foreach ($brackets as $bracket) {
            $rawCap = $bracket['up_to'] ?? null;
            $cap = $rawCap === null ? null : (float) $rawCap;
            $rate = (float) ($bracket['rate'] ?? 0) / 100;

            $bandSize = $cap === null ? $remaining : max(0, min($remaining, $cap - $previousCap));

            if ($bandSize <= 0) {
                if ($cap === null) {
                    break;
                }

                $previousCap = $cap;

                continue;
            }

            $tax += $bandSize * $rate;
            $remaining -= $bandSize;
            $previousCap = $cap ?? $previousCap;

            if ($remaining <= 0) {
                break;
            }
        }

        return round($tax, 2);
    }
}
