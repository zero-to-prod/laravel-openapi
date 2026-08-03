<?php

declare(strict_types=1);

use Laravel\Mcp\Server\Testing\TestResponse;
use ZeroToProd\LaravelOpenapi\Tests\TestCase;

pest()->extend(TestCase::class)->in(__DIR__);
pest()->tia()->locally();

function mcpText(TestResponse $response): string
{
    $content = new ReflectionMethod($response, 'content')->invoke($response);

    return implode("\n", array_filter(is_array($content) ? $content : [], is_string(...)));
}
