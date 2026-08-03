<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi\Internal\Mcp;

use Laravel\Mcp\Server\Tool;
use ZeroToProd\LaravelOpenapi\Internal\Mcp\Tools\Api;
use ZeroToProd\LaravelOpenapi\Internal\Mcp\Tools\Example;
use ZeroToProd\LaravelOpenapi\Internal\Mcp\Tools\Readme;
use ZeroToProd\LaravelOpenapi\Internal\Mcp\Tools\Status;

/** @internal */
class Server extends \Laravel\Mcp\Server
{
    protected string $name = 'Laravel OpenAPI';

    protected string $version = '1.0.0';

    protected string $instructions = <<<'MARKDOWN'
        This MCP server documents this package and reports how far this
        application has got with it.

        Call the `status` tool first when documenting endpoints, and again when
        you think you are finished. It reads the application's own routes, so it
        is the only tool here that knows which endpoints still declare nothing
        and which declared responses no test has reached. Nothing else can tell
        you that: the attribute is often a project-local subclass, and route URIs
        are frequently built at runtime, so neither is reliably greppable.

        Call the `example` tool before adding or changing an endpoint. It is a
        worked, verified example of the attribute, the route, the tests that
        prove the two agree, the CI gate, and what each failure message means.
        Read it once per session, not once per endpoint.

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
        Status::class,
        Example::class,
        Readme::class,
        Api::class,
    ];
}
