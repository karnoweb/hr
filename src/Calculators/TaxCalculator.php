<?php

namespace Karnoweb\Hr\Calculators;

use Carbon\Carbon;
use InvalidArgumentException;
use Karnoweb\Hr\Models\TaxBracket;

/**
 * Progressive income tax (HR-106 / HR-108 / HR-109 / HR-110).
 *
 * Policies (`hr.tax.method`):
 * - monthly_annualization: monthly taxable × 12 through annual brackets, then ÷ 12.
 * - ytd_reconciliation: annualize year-to-date taxable and withhold the unpaid delta.
 */
class TaxCalculator
{
    /**
     * @param  array{taxable?: float, tax?: float, months?: int}  $yearToDate
     * @return array{
     *     taxable_income: float,
     *     tax: float,
     *     annualized_taxable: float,
     *     annual_exemption_applied: float,
     *     method: string,
     *     tax_bracket_id: int|null,
     *     fiscal_year: int|null,
     *     rule_effective_date: string|null,
     *     ytd_taxable: float,
     *     ytd_tax_paid: float
     * }
     */
    public function calculateMonthly(
        float $monthlyTaxable,
        Carbon $asOfDate,
        int $dependentsCount = 0,
        float $additionalAnnualExemption = 0,
        bool $taxExempt = false,
        array $yearToDate = [],
    ): array {
        $monthlyTaxable = max(0, $monthlyTaxable);
        $method = (string) config('hr.tax.method', 'monthly_annualization');
        $annualTaxable = $monthlyTaxable * 12;
        $empty = [
            'taxable_income' => round($monthlyTaxable, 2),
            'tax' => 0.0,
            'annualized_taxable' => round($annualTaxable, 2),
            'annual_exemption_applied' => 0.0,
            'method' => $method,
            'tax_bracket_id' => null,
            'fiscal_year' => null,
            'rule_effective_date' => null,
            'ytd_taxable' => round((float) ($yearToDate['taxable'] ?? 0) + $monthlyTaxable, 2),
            'ytd_tax_paid' => round((float) ($yearToDate['tax'] ?? 0), 2),
        ];

        if ($taxExempt || ! config('hr.tax.enabled', true)) {
            return $empty;
        }

        $bracketSet = TaxBracket::forDate($asOfDate);

        if ($bracketSet === null) {
            throw new InvalidArgumentException('No tax bracket configuration for the payroll date.');
        }

        $annualExemption = (float) $bracketSet->annual_exemption
            + max(0, $additionalAnnualExemption)
            + $this->dependentsExemption($dependentsCount);

        if ($method === 'ytd_reconciliation') {
            $result = $this->calculateYearToDate(
                $monthlyTaxable,
                $annualExemption,
                $bracketSet->brackets,
                $yearToDate,
            );
        } else {
            $taxableAfterExemption = max(0, $annualTaxable - $annualExemption);
            $annualTax = $this->applyBrackets($taxableAfterExemption, $bracketSet->brackets);
            $result = [
                'taxable_income' => round($monthlyTaxable, 2),
                'tax' => round($annualTax / 12, 2),
                'annualized_taxable' => round($annualTaxable, 2),
                'annual_exemption_applied' => round($annualExemption, 2),
                'ytd_taxable' => $empty['ytd_taxable'],
                'ytd_tax_paid' => $empty['ytd_tax_paid'],
            ];
        }

        return array_merge($result, [
            'method' => $method,
            'tax_bracket_id' => $bracketSet->id,
            'fiscal_year' => $bracketSet->fiscal_year,
            'rule_effective_date' => $bracketSet->effective_date?->toDateString(),
        ]);
    }

    /**
     * @param  array{taxable?: float, tax?: float, months?: int}  $yearToDate
     * @param  array<int, array{up_to: int|float|null, rate: int|float}>  $brackets
     * @return array{
     *     taxable_income: float,
     *     tax: float,
     *     annualized_taxable: float,
     *     annual_exemption_applied: float,
     *     ytd_taxable: float,
     *     ytd_tax_paid: float
     * }
     */
    protected function calculateYearToDate(
        float $monthlyTaxable,
        float $annualExemption,
        array $brackets,
        array $yearToDate,
    ): array {
        $priorTaxable = max(0, (float) ($yearToDate['taxable'] ?? 0));
        $priorTax = max(0, (float) ($yearToDate['tax'] ?? 0));
        $monthsElapsed = max(1, (int) ($yearToDate['months'] ?? 0) + 1);

        $ytdTaxable = $priorTaxable + $monthlyTaxable;
        $annualizedTaxable = $ytdTaxable * 12 / $monthsElapsed;
        $taxableAfterExemption = max(0, $annualizedTaxable - $annualExemption);
        $annualTax = $this->applyBrackets($taxableAfterExemption, $brackets);
        $ytdTaxDue = $annualTax * $monthsElapsed / 12;
        $thisMonth = max(0, round($ytdTaxDue - $priorTax, 2));

        return [
            'taxable_income' => round($monthlyTaxable, 2),
            'tax' => $thisMonth,
            'annualized_taxable' => round($annualizedTaxable, 2),
            'annual_exemption_applied' => round($annualExemption, 2),
            'ytd_taxable' => round($ytdTaxable, 2),
            'ytd_tax_paid' => round($priorTax, 2),
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
