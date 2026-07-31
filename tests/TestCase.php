<?php

declare(strict_types=1);

namespace ZeroToProd\JsonApi\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use ZeroToProd\JsonApi\JsonApiServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * Get the package providers to register in the test application.
     *
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            JsonApiServiceProvider::class,
        ];
    }
}
