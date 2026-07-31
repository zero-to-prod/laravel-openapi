<?php

declare(strict_types=1);

namespace ZeroToProd\JsonApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Zerotoprod\DataModelOpenapi30\MediaType;
use Zerotoprod\DataModelOpenapi30\OpenApi;
use Zerotoprod\DataModelOpenapi30\Operation;
use Zerotoprod\DataModelOpenapi30\PathItem;
use Zerotoprod\DataModelOpenapi30\Response;
use Zerotoprod\DataModelOpenapi30\Schema;
use ZeroToProd\JsonApi\JsonApi;
use ZeroToProd\JsonApi\SchemaGenerator;

class SchemaController
{
    public function __construct(private readonly SchemaGenerator $generator)
    {
    }

    #[JsonApi([
        OpenApi::paths => [
            '/schema' => [
                PathItem::get => [
                    Operation::operationId => 'getSchema',
                    Operation::summary => 'Get the OpenAPI document for this API',
                    Operation::description => 'Returns an OpenAPI 3.0 document generated from the #[JsonApi] attributes declared on the registered routes.',
                    Operation::responses => [
                        '200' => [
                            Response::description => 'The generated OpenAPI document.',
                            Response::content => [
                                'application/json' => [
                                    MediaType::schema => [
                                        Schema::type => 'object',
                                        Schema::required => ['openapi', 'info', 'paths'],
                                        Schema::properties => [
                                            'openapi' => [Schema::type => 'string'],
                                            'info' => [Schema::type => 'object'],
                                            'paths' => [Schema::type => 'object'],
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
        return new JsonResponse($this->generator->generate()->toArray());
    }
}