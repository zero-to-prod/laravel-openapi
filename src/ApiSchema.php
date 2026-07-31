<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi;

use Attribute;
use Illuminate\Routing\Route as Registered;
use Illuminate\Support\Facades\Route;
use ZeroToProd\LaravelOpenapi\Http\Controllers\SchemaController;

/**
 * Declares the OpenAPI document fragment for the route handled by this method.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class ApiSchema
{
    /**
     * @param  array<string, mixed>  $schema
     */
    public function __construct(public readonly array $schema = [])
    {
    }

    /**
     * Register the package's routes with no prefix or middleware of their own,
     * so the caller decides where they live.
     *
     * Used by the package's own route file, and available to applications that
     * set `openapi.route.enabled` to false and place the route themselves.
     */
    public static function routes(?string $uri = null, ?string $name = null): Registered
    {
        return Route::get(
            $uri ?? config('openapi.route.uri', 'schema'),
            SchemaController::class,
        )->name($name ?? config('openapi.route.name', 'openapi.schema'));
    }
}