<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi\Tests\Fixtures;

use ZeroToProd\LaravelOpenapi\ApiSchema;

class ComponentReferenceController
{
    #[ApiSchema([
        'components' => [
            'schemas' => [
                'Article' => [
                    'type' => 'object',
                    'required' => ['author'],
                    'properties' => ['author' => ['$ref' => '#/components/schemas/Author']],
                ],
                'Author' => [
                    'type' => 'object',
                    'properties' => ['name' => ['type' => 'string']],
                ],
                'Unused' => ['type' => 'string'],
            ],
        ],
        'paths' => [
            '/component-reference' => [
                'get' => [
                    'operationId' => 'componentReference',
                    'responses' => [
                        '200' => [
                            'description' => 'An article.',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/Article'],
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
