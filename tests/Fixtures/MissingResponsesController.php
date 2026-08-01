<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi\Tests\Fixtures;

use ZeroToProd\LaravelOpenapi\ApiSchema;

class MissingResponsesController
{
    #[ApiSchema([
        'paths' => [
            '/missing-responses' => [
                'get' => [
                    'summary' => 'No responses declared',
                ],
            ],
        ],
    ])]
    public function __invoke(): void
    {
    }
}
