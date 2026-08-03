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
        Documents this package and reports how far this application has got with it.

        - `status` — routes declaring no schema, and declared responses no test
          reached. Not greppable: the attribute is usually a project-local
          subclass, and route URIs are often built at runtime. Call it first,
          and again to confirm.
        - `example` — how to write and test the attribute. Once per session, not
          once per endpoint.
        - `readme` — installation, routing, config, MCP setup, limitations.
        - `api` — exact signatures. Anything unlisted is internal: do not call it.
        MARKDOWN;

    /** @var array<int, class-string<Tool>> */
    protected array $tools = [
        Status::class,
        Example::class,
        Readme::class,
        Api::class,
    ];
}
