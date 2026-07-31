<?php

declare(strict_types=1);

namespace ZeroToProd\JsonApi\Tests\Fixtures;

use Zerotoprod\DataModelOpenapi30\MediaType;
use Zerotoprod\DataModelOpenapi30\OpenApi;
use Zerotoprod\DataModelOpenapi30\Operation;
use Zerotoprod\DataModelOpenapi30\PathItem;
use Zerotoprod\DataModelOpenapi30\Reference;
use Zerotoprod\DataModelOpenapi30\Response;
use ZeroToProd\JsonApi\JsonApi;

/**
 * References a component that no controller contributes.
 */
class DanglingReferenceController
{
    #[JsonApi([
        OpenApi::paths => [
            '/dangling-reference' => [
                PathItem::get => [
                    Operation::responses => [
                        '200' => [
                            Response::description => 'Refers to a missing schema.',
                            Response::content => [
                                'application/vnd.api+json' => [
                                    MediaType::schema => [
                                        Reference::ref => '#/components/schemas/DoesNotExist',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ])]
    public function __invoke(): void
    {
    }
}
