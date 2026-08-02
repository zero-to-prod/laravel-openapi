<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi\Internal\Mcp;

use Laravel\Mcp\Server\Tool;
use ZeroToProd\LaravelOpenapi\Internal\Mcp\Tools\Api;
use ZeroToProd\LaravelOpenapi\Internal\Mcp\Tools\Example;
use ZeroToProd\LaravelOpenapi\Internal\Mcp\Tools\Readme;

/** @internal */
class Server extends \Laravel\Mcp\Server
{
    protected string $name = 'Laravel OpenAPI';

    protected string $version = '1.0.0';

    protected string $instructions = <<<'MARKDOWN'
        This MCP server documents this package.

        Call the `example` tool before adding or changing an endpoint. It is the
        one to reach for by default: a worked, verified example of the attribute,
        the route, the tests that prove the two agree, the CI gate, and what each
        failure message means.

        Call the `readme` tool for anything the example does not cover —
        installation, routing and config options, the MCP server itself, or the
        package's known limitations.

        Call the `api` tool before calling into the package from your own code,
        to confirm a class or method exists and to get its exact signature.
        Anything it does not list is internal: do not call it, and do not assume
        it will still be there in the next release.
        MARKDOWN;

    /** @var array<int, class-string<Tool>> */
    protected array $tools = [
        Example::class,
        Readme::class,
        Api::class,
    ];
}
