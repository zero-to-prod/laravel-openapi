<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi\Tests\Fixtures;

use Illuminate\Http\JsonResponse;
use ZeroToProd\LaravelOpenapi\ApiSchema;

/**
 * A Path Item carries keys that are not operations. The `x-` extension below
 * holds a security-shaped array: scanning every key rather than the operation
 * allowlist would report `ghost` as a missing scheme.
 */
class PathLevelParametersController
{
    #[ApiSchema([
        'paths' => [
            '/path-level-parameters' => [
                'summary' => 'Not an operation.',
                'parameters' => [
                    ['name' => 'trace', 'in' => 'query', 'schema' => ['type' => 'string']],
                ],
                'x-internal' => ['security' => [['ghost' => []]]],
                'get' => [
                    'operationId' => 'pathLevelParameters',
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
