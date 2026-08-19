<?php

namespace Karnoweb\Hr;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Karnoweb\Hr\Console\Commands\AutoClockOutCommand;
use Karnoweb\Hr\Services\AttendanceService;
use Karnoweb\Hr\Services\ContractService;
use Karnoweb\Hr\Services\DocumentService;
use Karnoweb\Hr\Services\EmployeeService;
use Karnoweb\Hr\Services\LeaveService;
use Karnoweb\Hr\Services\ShiftAssignmentService;
use Karnoweb\Hr\Services\ShiftResolver;
use Karnoweb\Hr\Support\SequenceGenerator;
use Karnoweb\Hr\Support\WorkingDayCalculator;

class HrServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/hr.php',
            'hr'
        );

        $this->app->singleton(SequenceGenerator::class);
        $this->app->singleton(WorkingDayCalculator::class);
        $this->app->singleton(ShiftResolver::class);
        $this->app->singleton(ShiftAssignmentService::class);
        $this->app->singleton(AttendanceService::class);
        $this->app->singleton(EmployeeService::class);
        $this->app->singleton(ContractService::class);
        $this->app->singleton(LeaveService::class);
        $this->app->singleton(DocumentService::class);

        $this->app->singleton('hr', fn ($app) => new Hr($app));
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'hr');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/hr.php' => config_path('hr.php'),
            ], 'hr-config');

            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

            $this->commands([
                AutoClockOutCommand::class,
            ]);
        }

        $this->app->booted(function () {
            if (! $this->app->runningInConsole()) {
                return;
            }

            $schedule = $this->app->make(Schedule::class);
            $schedule->command('hr:auto-clock-out')->hourly();
        });
    }
}
