<?php

declare(strict_types=1);

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
        'prefix' => 'jsonapi',
        'middleware' => ['api'],
    ],

];
