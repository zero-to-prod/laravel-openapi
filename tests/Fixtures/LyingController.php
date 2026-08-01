<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi\Tests\Fixtures;

use Illuminate\Http\JsonResponse;
use ZeroToProd\LaravelOpenapi\ApiSchema;

/**
 * Declares a string `version` but returns an integer, and omits the declared
 * required `id` entirely. A valid document that describes untrue behavior.
 */
class LyingController
{
    #[ApiSchema([
        'paths' => [
            '/lying' => [
                'get' => [
                    'responses' => [
                        '200' => [
                            'description' => 'Claims a string version and a required id.',
                            'content' => [
                                'application/vnd.api+json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['version', 'id'],
                                        'properties' => [
                                            'version' => ['type' => 'string'],
                                            'id' => ['type' => 'string'],
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
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(
            data: ['version' => 123],
            headers: ['Content-Type' => 'application/vnd.api+json'],
        );
    }
}
