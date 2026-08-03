<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi\Tests\Fixtures;

use Illuminate\Http\JsonResponse;
use ZeroToProd\LaravelOpenapi\ApiSchema;

/**
 * Declares the scheme it requires. The second requirement is the empty object
 * that makes authentication optional; it names nothing and must not be flagged.
 */
class DeclaredSecuritySchemeController
{
    #[ApiSchema([
        'components' => [
            'securitySchemes' => [
                'sanctum' => ['type' => 'http', 'scheme' => 'bearer'],
            ],
        ],
        'paths' => [
            '/declared-security' => [
                'get' => [
                    'operationId' => 'declaredSecurity',
                    'security' => [['sanctum' => []], []],
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
