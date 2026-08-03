<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi\Tests\Fixtures;

use Illuminate\Http\JsonResponse;
use ZeroToProd\LaravelOpenapi\ApiSchema;

/** A second offender, so the gate can be shown to report both. */
class SecondUndeclaredSecurityController
{
    #[ApiSchema([
        'paths' => [
            '/also-undeclared' => [
                'post' => [
                    'operationId' => 'alsoUndeclared',
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
