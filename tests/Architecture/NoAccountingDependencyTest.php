<?php

namespace Karnoweb\Hr\Tests\Architecture;

use Karnoweb\Hr\Tests\TestCase;

/**
 * Guard against direct accounting package imports in HR services (HR-145).
 */
class NoAccountingDependencyTest extends TestCase
{
    public function test_services_and_calculators_do_not_reference_accounting_packages(): void
    {
        $forbidden = [
            'Karnoweb\\Accounting',
            'laravel-accounting',
            'AccountingServiceProvider',
        ];

        $paths = [
            dirname(__DIR__, 2).'/src/Services',
            dirname(__DIR__, 2).'/src/Calculators',
        ];

        foreach ($paths as $directory) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));

            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $contents = file_get_contents($file->getPathname());

                foreach ($forbidden as $needle) {
                    $this->assertStringNotContainsString(
                        $needle,
                        $contents,
                        "Forbidden accounting reference [{$needle}] in {$file->getPathname()}"
                    );
                }
            }
        }
    }
}
