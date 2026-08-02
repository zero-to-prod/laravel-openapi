<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi\Tests\Fixtures;

use Illuminate\Http\JsonResponse;
use ZeroToProd\LaravelOpenapi\ApiSchema;

class ShowArticleController
{
    #[ApiSchema([
        'paths' => [
            '/articles/{id}' => [
                'get' => [
                    'operationId' => 'showArticle',
                    'summary' => 'Get one article',
                    'parameters' => [
                        [
                            'name' => 'id',
                            'in' => 'path',
                            'required' => true,
                            'schema' => ['type' => 'string'],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'The article.',
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
                        '404' => [
                            'description' => 'No article has that id.',
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
    public function __invoke(string $id): JsonResponse
    {
        // Stands in for your own lookup.
        $title = $id === '42' ? 'Zero to prod' : null;

        if ($title === null) {
            return new JsonResponse(['message' => sprintf('No article has the id %s.', $id)], 404);
        }

        return new JsonResponse(['id' => $id, 'title' => $title]);
    }
}
