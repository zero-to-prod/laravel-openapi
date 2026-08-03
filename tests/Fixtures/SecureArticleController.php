<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi\Tests\Fixtures;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use ZeroToProd\LaravelOpenapi\ApiSchema;

class SecureArticleController
{
    #[ApiSchema([
        'components' => [
            'securitySchemes' => [
                'bearer' => ['type' => 'http', 'scheme' => 'bearer'],
            ],
        ],
        'paths' => [
            '/secure-articles' => [
                'get' => [
                    'operationId' => 'secureArticles',
                    'summary' => 'List articles for the authenticated caller',
                    'security' => [['bearer' => []]],
                    'responses' => [
                        '200' => [
                            'description' => 'The articles.',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['articles'],
                                        'properties' => [
                                            'articles' => ['type' => 'array', 'items' => ['type' => 'string']],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        '401' => [
                            'description' => 'The credential was missing or unrecognised.',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['message'],
                                        'properties' => [
                                            'message' => ['type' => 'string'],
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
    public function __invoke(Request $request): JsonResponse
    {
        // Stands in for your own guard.
        if ($request->bearerToken() !== 'valid-token') {
            return new JsonResponse(['message' => 'Unauthenticated.'], 401);
        }

        return new JsonResponse(['articles' => ['Zero to prod']]);
    }
}
