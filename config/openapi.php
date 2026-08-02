<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    |
    | Where the document is served. By default that is `/openapi.json` at the
    | root, which is independent of where your API itself lives — that is the
    | `servers` setting below. Publish this file with `php artisan
    | vendor:publish --tag=openapi-config` to override these values.
    |
    | Set `enabled` to false to register nothing, then call ApiSchema::routes()
    | from your own routes file to place the route yourself:
    |
    |     Route::middleware('auth:sanctum')
    |         ->prefix('internal')
    |         ->group(fn () => ApiSchema::routes());
    |
    | Changing `uri` moves the endpoint but does not move how it is documented:
    | SchemaController declares itself at `/openapi.json`. Keep them in step,
    | or accept that the document describes the old path.
    |
    */

    'route' => [
        'enabled' => true,
        'uri' => 'openapi.json',
        'name' => 'openapi.schema',
        'prefix' => '',
        'middleware' => ['api'],
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenAPI Document
    |--------------------------------------------------------------------------
    |
    | The document-level fields. The `paths` are generated from the #[ApiSchema]
    | attributes on your controllers, so only the fields that cannot be derived
    | are configured here. The title defaults to `APP_NAME`, read from the
    | environment rather than `config('app.name')` so this file does not depend
    | on the order config files are loaded in.
    |
    | Paths declared in those attributes are resolved relative to the first
    | server URL. With the default of `/` they are absolute, so declare the
    | path the route actually serves. If every endpoint sits under a common
    | base, set it here instead and drop it from the attributes.
    |
    */

    'openapi' => [
        'openapi' => '3.0.4',
        'info' => [
            'title' => env('APP_NAME', 'Laravel'),
            'version' => '1.0.0',
        ],
        'servers' => [
            ['url' => '/'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | MCP Server
    |--------------------------------------------------------------------------
    |
    | The package registers an MCP server so coding agents can read how it is
    | meant to be used. It requires laravel/mcp, and is a no-op without it:
    |
    |     composer require --dev laravel/mcp
    |     php artisan mcp:start laravel-openapi
    |
    | The `handle` is the name the server is registered under, which is the
    | argument to `mcp:start` and the name your agent refers to it by.
    |
    */

    'mcp' => [
        'enabled' => true,
        'handle' => 'laravel-openapi',
    ],

    /*
    |--------------------------------------------------------------------------
    | Schema Coverage
    |--------------------------------------------------------------------------
    |
    | Where the ValidatesSchema test trait appends the responses it validated,
    | so `php artisan openapi:coverage` can read them back from a separate
    | process. Append-only JSON Lines, so parallel test workers may share it.
    |
    | Reset it before a run and check it after:
    |
    |     php artisan openapi:coverage --reset && vendor/bin/pest \
    |         && php artisan openapi:coverage
    |
    */

    'coverage' => [
        'path' => storage_path('framework/cache/openapi-coverage.jsonl'),
    ],

];
