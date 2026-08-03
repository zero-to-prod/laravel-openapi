<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi\Tests\Fixtures;

use Illuminate\Http\JsonResponse;
use ZeroToProd\LaravelOpenapi\ApiSchema;

/** Requires a scheme no attribute declares, which league rejects at test time. */
class UndeclaredSecuritySchemeController
{
    #[ApiSchema([
        'paths' => [
            '/undeclared-security' => [
                'get' => [
                    'operationId' => 'undeclaredSecurity',
                    'security' => [['sanctum' => []]],
                    'responses' => [
                        '200' => ['description' => 'Anything.'],
                    ],
                ],
            ],
        ],
    ])]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse([]);
    }
}
