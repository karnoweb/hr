<?php

namespace Karnoweb\Hr\Services;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Karnoweb\Hr\Models\InsuranceRate;
use Karnoweb\Hr\Models\TaxBracket;

/**
 * Import versioned insurance/tax rates from a JSON payload (HR-111).
 */
class RatesImportService
{
    /**
     * @return array{
     *     insurance: array{action: string, id: int|null}|null,
     *     tax: array{action: string, id: int|null}|null
     * }
     */
    public function import(array $payload, bool $force = false): array
    {
        $this->validatePayload($payload);

        $result = ['insurance' => null, 'tax' => null];

        if (isset($payload['insurance'])) {
            $result['insurance'] = $this->importInsurance($payload['insurance'], $force);
        }

        if (isset($payload['tax'])) {
            $result['tax'] = $this->importTax($payload['tax'], $force);
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function parseFile(string $path): array
    {
        if (! is_readable($path)) {
            throw new InvalidArgumentException("Rates file is not readable: {$path}");
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new InvalidArgumentException("Unable to read rates file: {$path}");
        }

        $decoded = json_decode($contents, true);

        if (! is_array($decoded)) {
            throw new InvalidArgumentException('Rates file must contain valid JSON object.');
        }

        $this->validatePayload($decoded);

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function validatePayload(array $payload): void
    {
        if (! isset($payload['insurance']) && ! isset($payload['tax'])) {
            throw new InvalidArgumentException('JSON must include at least one of "insurance" or "tax".');
        }

        if (isset($payload['insurance'])) {
            $this->requireKeys($payload['insurance'], ['effective_date', 'employee_rate', 'employer_rate', 'unemployment_rate'], 'insurance');
        }

        if (isset($payload['tax'])) {
            $this->requireKeys($payload['tax'], ['fiscal_year', 'effective_date', 'annual_exemption', 'brackets'], 'tax');
            $this->validateBrackets($payload['tax']['brackets']);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{action: string, id: int|null}
     */
    protected function importInsurance(array $data, bool $force): array
    {
        $this->requireKeys($data, ['effective_date', 'employee_rate', 'employer_rate', 'unemployment_rate'], 'insurance');

        $effectiveDate = Carbon::parse((string) $data['effective_date'])->toDateString();
        $existing = InsuranceRate::query()->whereDate('effective_date', $effectiveDate)->first();

        if ($existing !== null && ! $force) {
            throw new InvalidArgumentException(
                "Insurance rate for effective_date {$effectiveDate} already exists. Pass --force to replace."
            );
        }

        $attributes = [
            'effective_date' => $effectiveDate,
            'employee_rate' => (float) $data['employee_rate'],
            'employer_rate' => (float) $data['employer_rate'],
            'unemployment_rate' => (float) $data['unemployment_rate'],
            'ceiling_multiplier' => (float) ($data['ceiling_multiplier'] ?? 7),
            'minimum_wage' => isset($data['minimum_wage'])
                ? (float) $data['minimum_wage']
                : (float) config('hr.payroll.minimum_wage', 0),
            'notes' => $data['notes'] ?? 'Imported via hr:import-rates — NEEDS VERIFICATION (legal/regulatory).',
        ];

        if ($existing !== null && $force) {
            $existing->update($attributes);

            return ['action' => 'updated', 'id' => $existing->id];
        }

        $created = InsuranceRate::query()->create($attributes);

        return ['action' => 'created', 'id' => $created->id];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{action: string, id: int|null}
     */
    protected function importTax(array $data, bool $force): array
    {
        $this->requireKeys($data, ['fiscal_year', 'effective_date', 'annual_exemption', 'brackets'], 'tax');
        $this->validateBrackets($data['brackets']);

        $effectiveDate = Carbon::parse((string) $data['effective_date'])->toDateString();
        $existing = TaxBracket::query()->whereDate('effective_date', $effectiveDate)->first();

        if ($existing !== null && ! $force) {
            throw new InvalidArgumentException(
                "Tax bracket set for effective_date {$effectiveDate} already exists. Pass --force to replace."
            );
        }

        $attributes = [
            'fiscal_year' => (int) $data['fiscal_year'],
            'effective_date' => $effectiveDate,
            'annual_exemption' => (float) $data['annual_exemption'],
            'brackets' => $data['brackets'],
            'notes' => $data['notes'] ?? 'Imported via hr:import-rates — NEEDS VERIFICATION (legal/regulatory).',
        ];

        if ($existing !== null && $force) {
            $existing->update($attributes);

            return ['action' => 'updated', 'id' => $existing->id];
        }

        $created = TaxBracket::query()->create($attributes);

        return ['action' => 'created', 'id' => $created->id];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $keys
     */
    protected function requireKeys(array $data, array $keys, string $section): void
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $data)) {
                throw new InvalidArgumentException("Missing required field \"{$key}\" in {$section} section.");
            }
        }
    }

    protected function validateBrackets(mixed $brackets): void
    {
        if (! is_array($brackets) || $brackets === []) {
            throw new InvalidArgumentException('Tax brackets must be a non-empty array.');
        }

        foreach ($brackets as $index => $bracket) {
            if (! is_array($bracket) || ! array_key_exists('rate', $bracket)) {
                throw new InvalidArgumentException("Tax bracket at index {$index} must include a rate.");
            }
        }
    }
}
