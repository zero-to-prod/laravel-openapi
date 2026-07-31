<?php

declare(strict_types=1);

namespace ZeroToProd\JsonApi\Http\Controllers;

use Illuminate\Http\JsonResponse;

class VersionController
{

    public function __invoke(): JsonResponse
    {
        return new JsonResponse(
            data: ['jsonapi' => ['version' => '1.1']],
            headers: ['Content-Type' => 'application/vnd.api+json'],
        );
    }
}
