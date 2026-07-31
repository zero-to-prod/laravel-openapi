<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi\Tests\Fixtures;

use Zerotoprod\DataModelOpenapi30\MediaType;
use Zerotoprod\DataModelOpenapi30\OpenApi;
use Zerotoprod\DataModelOpenapi30\Operation;
use Zerotoprod\DataModelOpenapi30\PathItem;
use Zerotoprod\DataModelOpenapi30\Response;
use Zerotoprod\DataModelOpenapi30\Schema;
use ZeroToProd\LaravelOpenapi\ApiSchema;

/**
 * Declares meaningful falsy values. Hydrating through the data model dropped
 * these, because its toArray() skips any value that is loosely false.
 */
class ZeroValuedController
{
    #[ApiSchema([
        OpenApi::paths => [
            '/zero-valued' => [
                PathItem::get => [
                    Operation::responses => [
                        '200' => [
                            Response::description => 'Bounded by zero.',
                            Response::content => [
                                'application/vnd.api+json' => [
                                    MediaType::schema => [
                                        Schema::type => 'integer',
                                        Schema::minimum => 0,
                                        Schema::example => 0,
                                        Schema::default => 0,
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
