<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi\Tests\Fixtures;

use Illuminate\Http\JsonResponse;
use Zerotoprod\DataModelOpenapi30\OpenApi;
use Zerotoprod\DataModelOpenapi30\Operation;
use Zerotoprod\DataModelOpenapi30\PathItem;
use Zerotoprod\DataModelOpenapi30\Response;
use ZeroToProd\LaravelOpenapi\ApiSchema;

/**
 * Declares only a 200 and then returns a status the document never mentions.
 */
class UndeclaredStatusController
{
    #[ApiSchema([
        OpenApi::paths => [
            '/undeclared-status' => [
                PathItem::get => [
                    Operation::responses => [
                        '200' => [
                            Response::description => 'The only declared response.',
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
