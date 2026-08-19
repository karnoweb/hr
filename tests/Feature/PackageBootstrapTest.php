<?php

namespace Karnoweb\Hr\Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Karnoweb\Hr\Facades\Hr;
use Karnoweb\Hr\HrServiceProvider;
use Karnoweb\Hr\Models\Employee;
use Karnoweb\Hr\Services\DocumentService;
use Karnoweb\Hr\Services\EmployeeService;
use Karnoweb\Hr\Services\LeaveService;
use Karnoweb\Hr\Support\SequenceGenerator;
use Karnoweb\Hr\Tests\TestCase;

class PackageBootstrapTest extends TestCase
{
    public function test_service_provider_is_registered(): void
    {
        $this->assertTrue($this->app->providerIsLoaded(HrServiceProvider::class));
    }

    public function test_hr_singleton_and_facade_resolve(): void
    {
        $this->assertTrue($this->app->bound('hr'));
        $this->assertSame($this->app->make('hr'), $this->app->make('hr'));
        $this->assertInstanceOf(EmployeeService::class, Hr::employees());
    }

    public function test_domain_services_are_container_singletons(): void
    {
        $this->assertSame(
            $this->app->make(EmployeeService::class),
            Hr::employees()
        );
        $this->assertSame(
            $this->app->make(LeaveService::class),
            Hr::leave()
        );
        $this->assertSame(
            $this->app->make(DocumentService::class),
            Hr::documents()
        );
        $this->assertSame(
            $this->app->make(SequenceGenerator::class),
            $this->app->make(SequenceGenerator::class)
        );
    }

    public function test_config_is_merged_and_readable(): void
    {
        $this->assertSame('hr_', config('hr.tables.prefix'));
        $this->assertSame('hr_', Hr::config('tables.prefix'));
    }

    public function test_package_migrations_create_core_tables(): void
    {
        $this->assertTrue(Schema::hasTable('hr_employees'));
        $this->assertTrue(Schema::hasTable('hr_documents'));
        $this->assertTrue(Schema::hasTable('hr_sequences'));
    }

    public function test_base_model_does_not_double_prefix_after_new_instance(): void
    {
        $original = new Employee;
        $clone = $original->newInstance();

        $this->assertSame('hr_employees', $original->getTable());
        $this->assertSame('hr_employees', $clone->getTable());
    }
}
