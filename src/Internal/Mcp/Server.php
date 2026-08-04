<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi\Internal\Mcp;

use Laravel\Mcp\Server\Tool;
use ZeroToProd\LaravelOpenapi\Internal\Mcp\Tools\Api;
use ZeroToProd\LaravelOpenapi\Internal\Mcp\Tools\Document;
use ZeroToProd\LaravelOpenapi\Internal\Mcp\Tools\Endpoint;
use ZeroToProd\LaravelOpenapi\Internal\Mcp\Tools\Example;
use ZeroToProd\LaravelOpenapi\Internal\Mcp\Tools\Readme;
use ZeroToProd\LaravelOpenapi\Internal\Mcp\Tools\Status;

/** @internal */
class Server extends \Laravel\Mcp\Server
{
    protected string $name = 'Laravel OpenAPI';

    protected string $version = '1.0.0';

    protected string $instructions = <<<'MARKDOWN'
        Documents this package and reports this application's state against it.
        Every state-reporting tool reads a freshly booted process and marks its
        answer stale when it could not.

        - `status` — routes declaring no schema, attribute classes in use, and
          declared responses no test reached. Counts, over a prefix or all routes.
        - `endpoint` — one endpoint in full: route, middleware, attribute,
          declared operation, components it references, per-response coverage.
        - `document` — the merged OpenAPI document as served, or one top-level key.
        - `example` — how to write and test the attribute. Once per session, not
          once per endpoint.
        - `readme` — installation, routing, config, MCP setup, limitations.
        - `api` — exact signatures. Anything unlisted is internal: do not call it.
        MARKDOWN;

    /** @var array<int, class-string<Tool>> */
    protected array $tools = [
        Status::class,
        Endpoint::class,
        Document::class,
        Example::class,
        Readme::class,
        Api::class,
    ];
}
