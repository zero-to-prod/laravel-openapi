<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Facades\Mcp;
use Override;
use ZeroToProd\LaravelOpenapi\Console\CoverageCommand;
use ZeroToProd\LaravelOpenapi\Console\InventoryCommand;
use ZeroToProd\LaravelOpenapi\Console\ValidateSchemaCommand;
use ZeroToProd\LaravelOpenapi\Internal\Mcp\Server;

/** @internal */
class LaravelOpenapiServiceProvider extends ServiceProvider
{
    /** @internal */
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/openapi.php', 'openapi');

        $this->app->singleton(SchemaGenerator::class, static fn (Application $app): SchemaGenerator => new SchemaGenerator(
            $app->make(Router::class),
            Config::array('openapi.openapi', []),
        ));
    }

    /** @internal */
    public function boot(): void
    {
        if (Config::boolean('openapi.route.enabled', true)) {
            $this->registerRoutes();
        }

        $this->registerMcpServer();

        if ($this->app->runningInConsole()) {
            $this->commands([
                CoverageCommand::class,
                InventoryCommand::class,
                ValidateSchemaCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/openapi.php' => config_path('openapi.php'),
            ], 'openapi-config');
        }
    }

    private function registerMcpServer(): void
    {
        // @codeCoverageIgnoreStart
        if (! class_exists(Mcp::class)) {
            return;
        }
        // @codeCoverageIgnoreEnd

        if (! Config::boolean('openapi.mcp.enabled', true)) {
            return;
        }

        Mcp::local(Config::string('openapi.mcp.handle', 'laravel-openapi'), Server::class);
    }

    private function registerRoutes(): void
    {
        Route::group([
            'prefix' => config('openapi.route.prefix'),
            'middleware' => config('openapi.route.middleware'),
        ], function (): void {
            $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        });
    }
}
