<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi\Tests\Fixtures;

class ConstantlessController
{
    #[ConstantlessSchema(ApiRoute::constantless)]
    public function __invoke(): void {}
}
