<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi\Tests\Fixtures;

class EnumConstructedController
{
    #[EnumConstructedSchema(ApiRoute::enumConstructed)]
    public function __invoke(): void {}
}
