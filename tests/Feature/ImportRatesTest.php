<?php

namespace Karnoweb\Hr\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Karnoweb\Hr\Models\InsuranceRate;
use Karnoweb\Hr\Models\TaxBracket;
use Karnoweb\Hr\Tests\TestCase;

class ImportRatesTest extends TestCase
{
    public function test_import_rates_command_creates_insurance_and_tax_rows(): void
    {
        $path = $this->ratesFixture([
            'insurance' => [
                'effective_date' => '2027-03-21',
                'employee_rate' => 7.5,
                'employer_rate' => 21,
                'unemployment_rate' => 3,
                'ceiling_multiplier' => 7,
            ],
            'tax' => [
                'fiscal_year' => 1406,
                'effective_date' => '2027-03-21',
                'annual_exemption' => 700_000_000,
                'brackets' => [
                    ['up_to' => 240_000_000, 'rate' => 10],
                    ['up_to' => null, 'rate' => 20],
                ],
            ],
        ]);

        $exitCode = Artisan::call('hr:import-rates', ['file' => $path]);

        $this->assertSame(0, $exitCode);
        $this->assertNotNull(InsuranceRate::query()->whereDate('effective_date', '2027-03-21')->first());
        $this->assertNotNull(TaxBracket::query()->whereDate('effective_date', '2027-03-21')->first());
    }

    public function test_import_rates_rejects_duplicate_effective_date_without_force(): void
    {
        InsuranceRate::query()->create([
            'effective_date' => '2027-01-01',
            'employee_rate' => 7,
            'employer_rate' => 20,
            'unemployment_rate' => 3,
            'ceiling_multiplier' => 7,
        ]);

        $path = $this->ratesFixture([
            'insurance' => [
                'effective_date' => '2027-01-01',
                'employee_rate' => 8,
                'employer_rate' => 20,
                'unemployment_rate' => 3,
                'ceiling_multiplier' => 7,
            ],
        ]);

        $exitCode = Artisan::call('hr:import-rates', ['file' => $path]);

        $this->assertSame(1, $exitCode);
    }

    public function test_import_rates_force_updates_existing_row(): void
    {
        $existing = InsuranceRate::query()->create([
            'effective_date' => '2027-02-01',
            'employee_rate' => 7,
            'employer_rate' => 20,
            'unemployment_rate' => 3,
            'ceiling_multiplier' => 7,
        ]);

        $path = $this->ratesFixture([
            'insurance' => [
                'effective_date' => '2027-02-01',
                'employee_rate' => 8,
                'employer_rate' => 21,
                'unemployment_rate' => 3,
                'ceiling_multiplier' => 7,
            ],
        ]);

        $exitCode = Artisan::call('hr:import-rates', [
            'file' => $path,
            '--force' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertSame(8.0, (float) $existing->fresh()->employee_rate);
    }

    public function test_import_rates_dry_run_does_not_persist(): void
    {
        $beforeInsurance = InsuranceRate::query()->count();
        $beforeTax = TaxBracket::query()->count();

        $path = $this->ratesFixture([
            'insurance' => [
                'effective_date' => '2028-01-01',
                'employee_rate' => 7,
                'employer_rate' => 20,
                'unemployment_rate' => 3,
                'ceiling_multiplier' => 7,
            ],
        ]);

        $exitCode = Artisan::call('hr:import-rates', [
            'file' => $path,
            '--dry-run' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertSame($beforeInsurance, InsuranceRate::query()->count());
        $this->assertSame($beforeTax, TaxBracket::query()->count());
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function ratesFixture(array $payload): string
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'hr-rates-'.uniqid('', true).'.json';
        file_put_contents($path, json_encode($payload, JSON_THROW_ON_ERROR));

        return $path;
    }
}
