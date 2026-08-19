<?php

namespace Karnoweb\Hr\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Karnoweb\Hr\HrServiceProvider;
use Karnoweb\Hr\Tests\Fixtures\User;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            HrServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        $app['config']->set('hr.tables.prefix', 'hr_');
        $app['config']->set('hr.models.user', User::class);
        $app['config']->set('hr.calendar.type', 'gregorian');
        $app['config']->set('hr.employee_code.auto_generate', true);
        $app['config']->set('hr.employee_code.format', '{year}-{sequence}');
        $app['config']->set('hr.employee_code.sequence_length', 4);
        $app['config']->set('hr.employee_code.sequence_per_branch', false);
        $app['config']->set('hr.employee_code.sequence_per_year', true);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });
    }

    protected function makeUser(array $attributes = []): User
    {
        return User::query()->create(array_merge([
            'name' => 'Test User',
            'email' => 'user-'.uniqid('', true).'@example.test',
        ], $attributes));
    }
}
