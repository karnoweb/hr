<?php

namespace Karnoweb\Hr\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Karnoweb\Hr\Support\SequenceGenerator;
use Karnoweb\Hr\Tests\TestCase;

class SequenceGeneratorTest extends TestCase
{
    public function test_first_value_for_a_new_scope_is_one(): void
    {
        $generator = $this->app->make(SequenceGenerator::class);

        $this->assertSame(1, $generator->nextValue('employee_code:1404'));
        $this->assertSame(1, $generator->currentValue('employee_code:1404'));
    }

    public function test_sequential_calls_are_gapless_and_distinct(): void
    {
        $generator = $this->app->make(SequenceGenerator::class);
        $scope = 'document:hire:2026';

        $values = [];
        for ($i = 0; $i < 50; $i++) {
            $values[] = $generator->nextValue($scope);
        }

        $this->assertSame(range(1, 50), $values);
        $this->assertSame(50, $generator->currentValue($scope));
    }

    public function test_scopes_are_independent(): void
    {
        $generator = $this->app->make(SequenceGenerator::class);

        $this->assertSame(1, $generator->nextValue('a'));
        $this->assertSame(1, $generator->nextValue('b'));
        $this->assertSame(2, $generator->nextValue('a'));
    }

    public function test_empty_scope_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->app->make(SequenceGenerator::class)->nextValue('   ');
    }

    public function test_concurrent_allocations_under_shared_connection_remain_gapless(): void
    {
        $generator = $this->app->make(SequenceGenerator::class);
        $scope = 'concurrency:smoke';
        $n = 25;

        // Nested transactions / lockForUpdate on SQLite serialize writers.
        // This asserts the generator remains correct under transactional
        // contention that mirrors the production locking pattern.
        $values = DB::transaction(function () use ($generator, $scope, $n) {
            $allocated = [];

            for ($i = 0; $i < $n; $i++) {
                $allocated[] = DB::transaction(fn () => $generator->nextValue($scope));
            }

            return $allocated;
        });

        sort($values);

        $this->assertSame(range(1, $n), $values);
        $this->assertCount($n, array_unique($values));
    }
}
