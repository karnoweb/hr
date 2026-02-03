<?php

namespace Karnoweb\Hr;

use Illuminate\Support\ServiceProvider;

class HrServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/hr.php',
            'hr'
        );

        $this->app->singleton('hr', fn () => new Hr());
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'hr');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/hr.php' => config_path('hr.php'),
            ], 'hr-config');

            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        }
    }
}
