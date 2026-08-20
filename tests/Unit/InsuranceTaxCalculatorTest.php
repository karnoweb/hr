<?php

namespace Karnoweb\Hr\Tests\Unit;

use Carbon\Carbon;
use Karnoweb\Hr\Calculators\InsuranceCalculator;
use Karnoweb\Hr\Calculators\TaxCalculator;
use Karnoweb\Hr\Models\InsuranceRate;
use Karnoweb\Hr\Models\TaxBracket;
use Karnoweb\Hr\Tests\TestCase;

class InsuranceTaxCalculatorTest extends TestCase
{
    public function test_insurance_calculator_applies_ceiling_from_rate_in_force(): void
    {
        config(['hr.payroll.minimum_wage' => 10_000_000]);

        InsuranceRate::query()->create([
            'effective_date' => '2025-01-01',
            'employee_rate' => 7,
            'employer_rate' => 20,
            'unemployment_rate' => 3,
            'ceiling_multiplier' => 5,
        ]);

        $calculator = new InsuranceCalculator;
        $result = $calculator->calculate(100_000_000, Carbon::parse('2025-06-01'));

        $this->assertSame(50_000_000.0, $result['capped_base']);
        $this->assertSame(3_500_000.0, $result['insurance_employee']);
    }

    public function test_insurance_lookup_uses_historical_rate_for_past_period(): void
    {
        config(['hr.payroll.minimum_wage' => 10_000_000]);

        InsuranceRate::query()->create([
            'effective_date' => '2024-01-01',
            'employee_rate' => 7,
            'employer_rate' => 20,
            'unemployment_rate' => 3,
            'ceiling_multiplier' => 7,
        ]);

        InsuranceRate::query()->create([
            'effective_date' => '2026-01-01',
            'employee_rate' => 8,
            'employer_rate' => 21,
            'unemployment_rate' => 3,
            'ceiling_multiplier' => 7,
        ]);

        $calculator = new InsuranceCalculator;
        $past = $calculator->calculate(20_000_000, Carbon::parse('2025-06-01'));
        $future = $calculator->calculate(20_000_000, Carbon::parse('2026-06-01'));

        $this->assertSame(1_400_000.0, $past['insurance_employee']);
        $this->assertSame(1_600_000.0, $future['insurance_employee']);
    }

    public function test_tax_calculator_monthly_annualization_policy(): void
    {
        TaxBracket::query()->create([
            'fiscal_year' => 1404,
            'effective_date' => '2026-01-01',
            'annual_exemption' => 0,
            'brackets' => [
                ['up_to' => 120_000_000, 'rate' => 10],
                ['up_to' => null, 'rate' => 20],
            ],
        ]);

        $calculator = new TaxCalculator;

        // Mechanics test only — values are not legal assertions.
        $result = $calculator->calculateMonthly(10_000_000, Carbon::parse('2026-03-01'));

        $this->assertSame(10_000_000.0, $result['taxable_income']);
        $this->assertSame(1_000_000.0, $result['tax']);
    }

    public function test_insurance_exempt_employee_pays_no_contributions(): void
    {
        config(['hr.payroll.minimum_wage' => 10_000_000]);

        InsuranceRate::query()->create([
            'effective_date' => '2025-01-01',
            'employee_rate' => 7,
            'employer_rate' => 20,
            'unemployment_rate' => 3,
            'ceiling_multiplier' => 7,
        ]);

        $calculator = new InsuranceCalculator;
        $result = $calculator->calculate(20_000_000, Carbon::parse('2025-06-01'), insuranceExempt: true);

        $this->assertSame(20_000_000.0, $result['insurable_base']);
        $this->assertSame(0.0, $result['insurance_employee']);
        $this->assertSame(0.0, $result['insurance_employer']);
    }

    public function test_tax_exempt_employee_pays_no_tax(): void
    {
        TaxBracket::query()->create([
            'fiscal_year' => 1404,
            'effective_date' => '2026-01-01',
            'annual_exemption' => 0,
            'brackets' => [
                ['up_to' => null, 'rate' => 10],
            ],
        ]);

        $calculator = new TaxCalculator;
        $result = $calculator->calculateMonthly(
            10_000_000,
            Carbon::parse('2026-03-01'),
            taxExempt: true,
        );

        $this->assertSame(0.0, $result['tax']);
    }

    public function test_additional_tax_exemption_reduces_withheld_tax(): void
    {
        TaxBracket::query()->create([
            'fiscal_year' => 1404,
            'effective_date' => '2026-01-01',
            'annual_exemption' => 0,
            'brackets' => [
                ['up_to' => null, 'rate' => 10],
            ],
        ]);

        $calculator = new TaxCalculator;
        $without = $calculator->calculateMonthly(10_000_000, Carbon::parse('2026-03-01'));
        $with = $calculator->calculateMonthly(
            10_000_000,
            Carbon::parse('2026-03-01'),
            additionalAnnualExemption: 60_000_000,
        );

        $this->assertSame(1_000_000.0, $without['tax']);
        $this->assertSame(500_000.0, $with['tax']);
    }

    public function test_insurance_uses_versioned_minimum_wage_on_the_rate(): void
    {
        config(['hr.payroll.minimum_wage' => 10_000_000]);

        InsuranceRate::query()->create([
            'effective_date' => '2025-01-01',
            'employee_rate' => 7,
            'employer_rate' => 20,
            'unemployment_rate' => 3,
            'ceiling_multiplier' => 5,
            'minimum_wage' => 4_000_000,
        ]);

        $calculator = new InsuranceCalculator;
        $result = $calculator->calculate(100_000_000, Carbon::parse('2025-06-01'));

        $this->assertSame(20_000_000.0, $result['capped_base']);
        $this->assertSame(4_000_000.0, $result['minimum_wage']);
    }

    public function test_tax_ytd_reconciliation_uses_prior_months(): void
    {
        config(['hr.tax.method' => 'ytd_reconciliation']);

        TaxBracket::query()->create([
            'fiscal_year' => 1404,
            'effective_date' => '2026-01-01',
            'annual_exemption' => 0,
            'brackets' => [
                ['up_to' => 120_000_000, 'rate' => 10],
                ['up_to' => null, 'rate' => 20],
            ],
        ]);

        $calculator = new TaxCalculator;
        $month1 = $calculator->calculateMonthly(5_000_000, Carbon::parse('2026-01-31'), yearToDate: [
            'taxable' => 0,
            'tax' => 0,
            'months' => 0,
        ]);
        $month2 = $calculator->calculateMonthly(20_000_000, Carbon::parse('2026-02-28'), yearToDate: [
            'taxable' => 5_000_000,
            'tax' => $month1['tax'],
            'months' => 1,
        ]);

        config(['hr.tax.method' => 'monthly_annualization']);
        $monthlyOnly = (new TaxCalculator)->calculateMonthly(20_000_000, Carbon::parse('2026-02-28'));

        $this->assertNotEquals($monthlyOnly['tax'], $month2['tax']);
        $this->assertSame('ytd_reconciliation', $month2['method']);
        $this->assertSame(25_000_000.0, $month2['ytd_taxable']);
        $this->assertNotNull($month2['tax_bracket_id']);
    }

    public function test_dependents_exemption_applies_when_config_enabled(): void
    {
        config([
            'hr.tax.dependents_exemption.enabled' => true,
            'hr.tax.dependents_exemption.per_dependent_annual' => 12_000_000,
        ]);

        TaxBracket::query()->create([
            'fiscal_year' => 1404,
            'effective_date' => '2026-01-01',
            'annual_exemption' => 0,
            'brackets' => [
                ['up_to' => null, 'rate' => 10],
            ],
        ]);

        $calculator = new TaxCalculator;
        $result = $calculator->calculateMonthly(
            10_000_000,
            Carbon::parse('2026-03-01'),
            dependentsCount: 2,
        );

        $this->assertSame(24_000_000.0, $result['annual_exemption_applied']);
        $this->assertSame(800_000.0, $result['tax']);
    }
}
