<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi\Tests\Fixtures;

use ZeroToProd\LaravelOpenapi\ApiSchema;

/**
 * Declares meaningful falsy values. Hydrating through the data model dropped
 * these, because its toArray() skips any value that is loosely false.
 */
class ZeroValuedController
{
    #[ApiSchema([
        'paths' => [
            '/zero-valued' => [
                'get' => [
                    'responses' => [
                        '200' => [
                            'description' => 'Bounded by zero.',
                            'content' => [
                                'application/vnd.api+json' => [
                                    'schema' => [
                                        'type' => 'integer',
                                        'minimum' => 0,
                                        'example' => 0,
                                        'default' => 0,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ])]
    public function __invoke(): void
    {
    }
}
