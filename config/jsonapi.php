<?php

declare(strict_types=1);

$prefix = 'jsonapi';

return [

    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    |
    | The prefix and middleware applied to every route registered by this
    | package. Publish this file with `php artisan vendor:publish
    | --tag=jsonapi-config` to override these values.
    |
    */

    'route' => [
        'prefix' => $prefix,
        'middleware' => ['api'],
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenAPI Document
    |--------------------------------------------------------------------------
    |
    | The document-level fields of the schema served at `/schema`. The `paths`
    | are generated from the #[JsonApi] attributes on your controllers, so
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

];
