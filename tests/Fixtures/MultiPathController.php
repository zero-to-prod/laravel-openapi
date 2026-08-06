<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi\Tests\Fixtures;

use Illuminate\Http\JsonResponse;
use ZeroToProd\LaravelOpenapi\ApiSchema;

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
