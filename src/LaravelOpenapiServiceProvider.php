<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use ZeroToProd\LaravelOpenapi\Console\CoverageCommand;
use ZeroToProd\LaravelOpenapi\Console\ValidateSchemaCommand;

class LaravelOpenapiServiceProvider extends ServiceProvider
{
    /**
     * Register any package services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/openapi.php', 'openapi');

        $this->app->singleton(SchemaGenerator::class, static fn (Application $app): SchemaGenerator => new SchemaGenerator(
            $app['router'],
            $app['config']->get('openapi.openapi', []),
        ));
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        if ($this->app['config']->get('openapi.route.enabled', true)) {
            $this->registerRoutes();
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                CoverageCommand::class,
                ValidateSchemaCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/openapi.php' => config_path('openapi.php'),
            ], 'openapi-config');
        }
    }

    /**
     * Register the package's routes.
     */
    protected function registerRoutes(): void
    {
        Route::group([
            'prefix' => config('openapi.route.prefix'),
            'middleware' => config('openapi.route.middleware'),
        ], function (): void {
            $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        });
    }
}
