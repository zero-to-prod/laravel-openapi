<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi\Tests;

use Illuminate\Contracts\Config\Repository;
use Orchestra\Testbench\TestCase as Orchestra;
use ZeroToProd\LaravelOpenapi\Internal\ValidatesSchema;
use ZeroToProd\LaravelOpenapi\LaravelOpenapiServiceProvider;

abstract class TestCase extends Orchestra
{
    use ValidatesSchema;

    /**
     * Config applied while the application is being created.
     *
     * @var array<string, mixed>
     */
    protected array $environmentConfig = [];

    /**
     * Get the package providers to register in the test application.
     *
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LaravelOpenapiServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $config = $app->make(Repository::class);

        foreach ($this->environmentConfig as $key => $value) {
            $config->set($key, $value);
        }
    }

    /**
     * Rebuild the application so the provider boots against the given config.
     * Route registration happens during boot, so setting config afterwards is
     * too late to affect it.
     *
     * @param  array<string, mixed>  $config
     */
    protected function withConfig(array $config): static
    {
        $this->environmentConfig = $config;

        $this->refreshApplication();

        return $this;
    }
}
