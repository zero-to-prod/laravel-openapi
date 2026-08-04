<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi\Tests\Fixtures;

use Illuminate\Http\JsonResponse;
use ZeroToProd\LaravelOpenapi\ApiSchema;

/**
 * Declares two paths from one attribute, which is legitimate: the same handler
 * may be registered under an alias. Only one of them can be the route this
 * attribute annotates, so the path check has to accept the pair.
 */
class MultiPathController
{
    private const array operation = [
        'get' => [
            'responses' => [
                '200' => ['description' => 'Anything.'],
            ],
        ],
    ];

    #[ApiSchema([
        'paths' => [
            '/aliased/{id}' => self::operation,
            '/canonical/{id}' => self::operation,
        ],
    ])]
    public function __invoke(string $id): JsonResponse
    {
        return new JsonResponse(['id' => $id]);
    }
}
