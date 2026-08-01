<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi\Tests\Fixtures;

use ZeroToProd\LaravelOpenapi\ApiSchema;

class DanglingReferenceController
{
    #[ApiSchema([
        'paths' => [
            '/dangling-reference' => [
                'get' => [
                    'responses' => [
                        '200' => [
                            'description' => 'Refers to a missing schema.',
                            'content' => [
                                'application/vnd.api+json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/DoesNotExist',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ])]
    public function __invoke(): void {}
}
