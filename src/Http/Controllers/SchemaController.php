<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use ZeroToProd\LaravelOpenapi\ApiSchema;
use ZeroToProd\LaravelOpenapi\SchemaGenerator;

/** @internal */
readonly class SchemaController
{
    #[ApiSchema([
        'paths' => [
            '/openapi.json' => [
                'get' => [
                    'operationId' => 'getSchema',
                    'summary' => 'Get the OpenAPI document for this API',
                    'description' => 'Returns an OpenAPI 3.0 document generated from the #[ApiSchema] attributes declared on the registered routes.',
                    'responses' => [
                        '200' => [
                            'description' => 'The generated OpenAPI document.',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['openapi', 'info', 'paths'],
                                        'properties' => [
                                            'openapi' => ['type' => 'string'],
                                            'info' => ['type' => 'object'],
                                            'paths' => ['type' => 'object'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ])]
    public function __invoke(SchemaGenerator $SchemaGenerator): JsonResponse
    {
        return new JsonResponse($SchemaGenerator->document());
    }
}
