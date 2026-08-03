<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi\Tests\Fixtures;

use Attribute;
use ZeroToProd\LaravelOpenapi\ApiSchema;

/**
 * A subclass taking no fragment and keeping none in a constant either, so there
 * is nowhere to point an agent but the call site.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class ConstantlessSchema extends ApiSchema
{
    public function __construct(ApiRoute $ApiRoute)
    {
        parent::__construct(['paths' => [$ApiRoute->value => ['get' => ['operationId' => 'getConstantless']]]]);
    }
}
