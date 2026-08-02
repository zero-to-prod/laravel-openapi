<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi\Tests\Fixtures;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use ZeroToProd\LaravelOpenapi\ApiSchema;

class StoreArticleController
{
    #[ApiSchema([
        'paths' => [
            '/articles' => [
                'post' => [
                    'operationId' => 'storeArticle',
                    'summary' => 'Create an article',
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'required' => ['title'],
                                    'properties' => [
                                        'title' => ['type' => 'string'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'responses' => [
                        '201' => [
                            'description' => 'The created article.',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['id', 'title'],
                                        'properties' => [
                                            'id' => ['type' => 'string'],
                                            'title' => ['type' => 'string'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        '422' => [
                            'description' => 'The title was blank.',
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
        $title = trim($request->string('title')->toString());

        if ($title === '') {
            return new JsonResponse(['message' => 'The title field is required.'], 422);
        }

        // Stands in for your own persistence.
        return new JsonResponse(['id' => '42', 'title' => $title], 201);
    }
}
