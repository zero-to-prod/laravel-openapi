<?php

declare(strict_types=1);

namespace ZeroToProd\JsonApi\Tests\Fixtures;

use Illuminate\Http\JsonResponse;
use Zerotoprod\DataModelOpenapi30\MediaType;
use Zerotoprod\DataModelOpenapi30\OpenApi;
use Zerotoprod\DataModelOpenapi30\Operation;
use Zerotoprod\DataModelOpenapi30\PathItem;
use Zerotoprod\DataModelOpenapi30\Response;
use Zerotoprod\DataModelOpenapi30\Schema;
use ZeroToProd\JsonApi\JsonApi;

/**
 * Declares a string `version` but returns an integer, and omits the declared
 * required `id` entirely. A valid document that describes untrue behavior.
 */
class LyingController
{
    #[JsonApi([
        OpenApi::paths => [
            '/lying' => [
                PathItem::get => [
                    Operation::responses => [
                        '200' => [
                            Response::description => 'Claims a string version and a required id.',
                            Response::content => [
                                'application/vnd.api+json' => [
                                    MediaType::schema => [
                                        Schema::type => 'object',
                                        Schema::required => ['version', 'id'],
                                        Schema::properties => [
                                            'version' => [Schema::type => 'string'],
                                            'id' => [Schema::type => 'string'],
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
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(
            data: ['version' => 123],
            headers: ['Content-Type' => 'application/vnd.api+json'],
        );
    }
}
