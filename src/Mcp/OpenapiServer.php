<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi\Mcp;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Tool;
use ZeroToProd\LaravelOpenapi\Mcp\Tools\Readme;

/** @internal */
class OpenapiServer extends Server
{
    protected string $name = 'Laravel OpenAPI';

    protected string $version = '1.0.0';

    protected string $instructions = <<<'MARKDOWN'
        This MCP server documents the zero-to-prod/laravel-openapi package, which
        generates an OpenAPI document from #[ApiSchema] attributes on controllers,
        serves it over HTTP, and validates both the document and real responses
        against it.

        Call the `readme` tool before writing or changing any code that uses this
        package, so the attribute shapes, commands, and test trait are used as
        the package intends.
        MARKDOWN;

    /** @var array<int, class-string<Tool>> */
    protected array $tools = [
        Readme::class,
    ];
}
