<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi\Tests\Fixtures;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Middleware a route runs but cannot name. `HasMiddleware` may return closures,
 * which reach `gatherMiddleware()` as objects — and an inventory carrying one
 * could not be encoded as JSON at all.
 */
class ClosureMiddlewareController implements HasMiddleware
{
    /** @return array<int, Closure> */
    public static function middleware(): array
    {
        return [static fn (Request $request, Closure $next): mixed => $next($request)];
    }

    public function __invoke(): void {}
}
