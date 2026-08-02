<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi\Tests\Fixtures;

use Attribute;
use ZeroToProd\LaravelOpenapi\ApiSchema;

#[Attribute(Attribute::TARGET_METHOD)]
class SubApiSchema extends ApiSchema {}
