<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi\Tests\Fixtures;

class SubclassSchemaController
{
    #[SubApiSchema([
        'paths' => [
            '/sub' => [
                'get' => ['operationId' => 'getSub'],
            ],
        ],
    ])]
    public function __invoke(): void {}
}
