<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi\Tests\Fixtures;

use Illuminate\Http\JsonResponse;
use ZeroToProd\LaravelOpenapi\ApiSchema;

/**
 * Declares only a 200 and then returns a status the document never mentions.
 */
class UndeclaredStatusController
{
    #[ApiSchema([
        'paths' => [
            '/undeclared-status' => [
                'get' => [
                    'responses' => [
                        '200' => [
                            'description' => 'The only declared response.',
                        ],
                    ],
                ],
            ],
        ],
    ])]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(['error' => 'teapot'], 418);
    }
}
