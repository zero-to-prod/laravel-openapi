<?php

declare(strict_types=1);

namespace ZeroToProd\JsonApi\Tests\Fixtures;

use Zerotoprod\DataModelOpenapi30\OpenApi;
use Zerotoprod\DataModelOpenapi30\Operation;
use Zerotoprod\DataModelOpenapi30\PathItem;
use ZeroToProd\JsonApi\JsonApi;

/**
 * Declares an operation without the required `responses` field.
 */
class MissingResponsesController
{
    #[JsonApi([
        OpenApi::paths => [
            '/missing-responses' => [
                PathItem::get => [
                    Operation::summary => 'No responses declared',
                ],
            ],
        ],
    ])]
    public function __invoke(): void
    {
    }
}
