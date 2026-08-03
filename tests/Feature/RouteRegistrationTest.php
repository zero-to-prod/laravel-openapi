<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\AssertionFailedError;
use ZeroToProd\LaravelOpenapi\ApiSchema;

function openApiRoutes(): array
{
    return collect(Route::getRoutes())
        ->filter(static fn ($route): bool => $route->getName() !== null && str_starts_with((string) $route->getName(), 'openapi.'))
        ->map(static fn ($route): string => $route->getName().' => '.$route->uri())
        ->values()
        ->all();
}

it('registers the route by default', function (): void {
    expect(openApiRoutes())->toBe(['openapi.schema => openapi.json']);

    $this->getJson('openapi.json')->assertOk();
});

it('registers nothing when the route is disabled', function (): void {
    $this->withConfig(['openapi.route.enabled' => false]);

    expect(openApiRoutes())->toBeEmpty();

    $this->getJson('openapi.json')->assertNotFound();
});

it('honours a configured uri', function (): void {
    $this->withConfig(['openapi.route.uri' => 'docs.json']);

    expect(openApiRoutes())->toBe(['openapi.schema => docs.json']);

    $this->getJson('docs.json')->assertOk();
    $this->getJson('openapi.json')->assertNotFound();
});

it('surfaces the document drift a configured uri causes', function (): void {
    // SchemaController declares itself at `/openapi.json`, so moving the route
    // leaves the document describing a path that is no longer served. The
    // response validator is what catches it.
    $this->withConfig(['openapi.route.uri' => 'docs.json']);

    expect($this->getJson('docs.json')->json('paths'))->toHaveKey('/openapi.json');

    $failure = null;

    try {
        $this->assertMatchesSchema($this->getJson('docs.json'));
    } catch (AssertionFailedError $e) {
        $failure = $e->getMessage();
    }

    expect($failure)->not->toBeNull('Moving the route went unnoticed.')
        ->and($failure)->toContain('no such operation');
});

it('honours a configured name', function (): void {
    $this->withConfig(['openapi.route.name' => 'docs.openapi']);

    expect(route('docs.openapi', absolute: false))->toBe('/openapi.json');
});

it('honours a configured prefix', function (): void {
    $this->withConfig(['openapi.route.prefix' => 'internal/docs']);

    expect(openApiRoutes())->toBe(['openapi.schema => internal/docs/openapi.json']);

    $this->getJson('internal/docs/openapi.json')->assertOk();
});

it('lets an application place the route itself when disabled', function (): void {
    $this->withConfig(['openapi.route.enabled' => false]);

    Route::prefix('internal')->group(static fn (): Illuminate\Routing\Route => ApiSchema::routes());

    expect(openApiRoutes())->toBe(['openapi.schema => internal/openapi.json']);

    $this->getJson('internal/openapi.json')->assertOk();
});

it('accepts an explicit uri and name from the caller', function (): void {
    $this->withConfig(['openapi.route.enabled' => false]);

    ApiSchema::routes('docs/openapi.json', 'docs.schema');

    expect(openApiRoutes())->toBeEmpty()
        ->and(route('docs.schema', absolute: false))->toBe('/docs/openapi.json');

    $this->getJson('docs/openapi.json')->assertOk();
});

it('returns the route so the caller can keep configuring it', function (): void {
    $this->withConfig(['openapi.route.enabled' => false]);

    $route = ApiSchema::routes()->middleware('throttle:5,1');

    expect($route->gatherMiddleware())->toContain('throttle:5,1');
});
