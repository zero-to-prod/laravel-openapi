<?php

declare(strict_types=1);

namespace ZeroToProd\JsonApi;

use Attribute;

/**
 * Declares the OpenAPI document fragment for the route handled by this method.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class JsonApi
{
    /**
     * @param  array<string, mixed>  $schema
     */
    public function __construct(public readonly array $schema = [])
    {
    }
}
