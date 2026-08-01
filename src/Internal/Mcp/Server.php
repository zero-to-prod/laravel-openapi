<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi\Internal\Mcp;

use Laravel\Mcp\Server\Tool;
use ZeroToProd\LaravelOpenapi\Internal\Mcp\Tools\Api;
use ZeroToProd\LaravelOpenapi\Internal\Mcp\Tools\Readme;

/** @internal */
class Server extends \Laravel\Mcp\Server
{
    protected string $name = 'Laravel OpenAPI';

    protected string $version = '1.0.0';

    protected string $instructions = <<<'MARKDOWN'
        This MCP server documents this package.

        Call the `readme` tool before writing or changing any code that uses this
        package, so the attribute shapes, commands, and test trait are used as
        the package intends.

        Call the `api` tool before calling into the package from your own code,
        to confirm a class or method exists and to get its exact signature.
        Anything it does not list is internal: do not call it, and do not assume
        it will still be there in the next release.
        MARKDOWN;

    /** @var array<int, class-string<Tool>> */
    protected array $tools = [
        Readme::class,
        Api::class,
    ];
}
