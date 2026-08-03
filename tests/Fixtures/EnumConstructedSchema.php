<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi\Tests\Fixtures;

use Attribute;
use ZeroToProd\LaravelOpenapi\ApiSchema;

/** @phpstan-import-type PathItem from ApiSchema */
#[Attribute(Attribute::TARGET_METHOD)]
class EnumConstructedSchema extends ApiSchema
{
    /** @var array<string, PathItem> */
    public const array paths = [
        '/enum-constructed' => [
            'get' => ['operationId' => 'getEnumConstructed'],
        ],
    ];

    public function __construct(ApiRoute $ApiRoute)
    {
        parent::__construct(['paths' => [$ApiRoute->value => static::paths[$ApiRoute->value]]]);
    }
}
