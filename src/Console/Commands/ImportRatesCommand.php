<?php

namespace Karnoweb\Hr\Console\Commands;

use Illuminate\Console\Command;
use Karnoweb\Hr\Services\RatesImportService;
use Throwable;

/**
 * Import versioned insurance/tax rates from JSON (HR-111).
 */
class ImportRatesCommand extends Command
{
    protected $signature = 'hr:import-rates
                            {file : Path to JSON file with insurance and/or tax sections}
                            {--dry-run : Validate the file without writing to the database}
                            {--force : Replace an existing row with the same effective_date}';

    protected $description = 'Import insurance and/or tax rates into versioned tables';

    public function handle(RatesImportService $importer): int
    {
        $path = (string) $this->argument('file');

        try {
            $payload = $importer->parseFile($path);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run — JSON structure is valid.');
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        try {
            $result = $importer->import($payload, (bool) $this->option('force'));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($result['insurance'] !== null) {
            $this->info(sprintf(
                'Insurance rate %s (id %d).',
                $result['insurance']['action'],
                $result['insurance']['id']
            ));
        }

        if ($result['tax'] !== null) {
            $this->info(sprintf(
                'Tax bracket set %s (id %d).',
                $result['tax']['action'],
                $result['tax']['id']
            ));
        }

        return self::SUCCESS;
    }
}
