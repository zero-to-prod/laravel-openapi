<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi\Tests\Fixtures;

use Zerotoprod\DataModelOpenapi30\OpenApi;
use Zerotoprod\DataModelOpenapi30\Operation;
use Zerotoprod\DataModelOpenapi30\PathItem;
use ZeroToProd\LaravelOpenapi\ApiSchema;

/**
 * Declares an operation without the required `responses` field.
 */
class MissingResponsesController
{
    #[ApiSchema([
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
