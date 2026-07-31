<?php

declare(strict_types=1);

namespace ZeroToProd\JsonApi;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class JsonApiServiceProvider extends ServiceProvider
{
    /**
     * Register any package services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/jsonapi.php', 'jsonapi');
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        $this->registerRoutes();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/jsonapi.php' => config_path('jsonapi.php'),
            ], 'jsonapi-config');
        }
    }

    /**
     * Register the package's routes.
     */
    protected function registerRoutes(): void
    {
        Route::group([
            'prefix' => config('jsonapi.route.prefix'),
            'middleware' => config('jsonapi.route.middleware'),
        ], function (): void {
            $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        });
    }
}
