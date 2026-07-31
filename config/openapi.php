<?php

declare(strict_types=1);

$prefix = 'openapi';

return [

    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    |
    | The route this package registers. Publish this file with `php artisan
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
    | SchemaController declares itself at `/schema`. Keep them in step, or
    | accept that the document describes the old path.
    |
    */

    'route' => [
        'enabled' => true,
        'uri' => 'schema',
        'name' => 'openapi.schema',
        'prefix' => $prefix,
        'middleware' => ['api'],
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenAPI Document
    |--------------------------------------------------------------------------
    |
    | The document-level fields of the schema served at `/schema`. The `paths`
    | are generated from the #[ApiSchema] attributes on your controllers, so
    | only the fields that cannot be derived are configured here.
    |
    | Paths declared in those attributes omit the route prefix above, so it is
    | published as the server URL instead.
    |
    */

    'openapi' => [
        'openapi' => '3.0.4',
        'info' => [
            'title' => 'JSON:API',
            'version' => '1.0.0',
        ],
        'servers' => [
            ['url' => '/'.$prefix],
        ],
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
