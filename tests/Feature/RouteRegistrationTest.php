<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use ZeroToProd\LaravelOpenapi\ApiSchema;

function openApiRoutes(): array
{
    return collect(Route::getRoutes())
        ->filter(static fn ($route) => $route->getName() !== null && str_starts_with((string) $route->getName(), 'openapi.'))
        ->map(static fn ($route) => $route->getName().' => '.$route->uri())
        ->values()
        ->all();
}

it('registers the route by default', function (): void {
    expect(openApiRoutes())->toBe(['openapi.schema => openapi/schema']);

    $this->getJson('openapi/schema')->assertOk();
});

it('registers nothing when the route is disabled', function (): void {
    $this->withConfig(['openapi.route.enabled' => false]);

    expect(openApiRoutes())->toBe([]);

    $this->getJson('openapi/schema')->assertNotFound();
});

it('honours a configured uri', function (): void {
    $this->withConfig(['openapi.route.uri' => 'openapi']);

    expect(openApiRoutes())->toBe(['openapi.schema => openapi/openapi']);

    $this->getJson('openapi/openapi')->assertOk();
    $this->getJson('openapi/schema')->assertNotFound();
});

it('surfaces the document drift a configured uri causes', function (): void {
    // SchemaController declares itself at `/schema`, so moving the route leaves
    // the document describing a path that is no longer served. The response
    // validator is what catches it.
    $this->withConfig(['openapi.route.uri' => 'openapi']);

    expect($this->getJson('openapi/openapi')->json('paths'))->toHaveKey('/schema');

    $failure = null;

    try {
        $this->assertMatchesSchema($this->getJson('openapi/openapi'));
    } catch (PHPUnit\Framework\AssertionFailedError $e) {
        $failure = $e->getMessage();
    }

    expect($failure)->not->toBeNull('Moving the route went unnoticed.')
        ->and($failure)->toContain('no such operation');
});

it('honours a configured name', function (): void {
    $this->withConfig(['openapi.route.name' => 'docs.openapi']);

    expect(route('docs.openapi', absolute: false))->toBe('/openapi/schema');
});

it('honours a configured prefix', function (): void {
    $this->withConfig(['openapi.route.prefix' => 'internal/docs']);

    expect(openApiRoutes())->toBe(['openapi.schema => internal/docs/schema']);

    $this->getJson('internal/docs/schema')->assertOk();
});

it('lets an application place the route itself when disabled', function (): void {
    $this->withConfig(['openapi.route.enabled' => false]);

    Route::prefix('internal')->group(static fn () => ApiSchema::routes());

    expect(openApiRoutes())->toBe(['openapi.schema => internal/schema']);

    $this->getJson('internal/schema')->assertOk();
});

it('accepts an explicit uri and name from the caller', function (): void {
    $this->withConfig(['openapi.route.enabled' => false]);

    ApiSchema::routes('openapi.json', 'docs.schema');

    expect(openApiRoutes())->toBe([])
        ->and(route('docs.schema', absolute: false))->toBe('/openapi.json');

    $this->getJson('openapi.json')->assertOk();
});

it('returns the route so the caller can keep configuring it', function (): void {
    $this->withConfig(['openapi.route.enabled' => false]);

    $route = ApiSchema::routes()->middleware('throttle:5,1');

    expect($route->gatherMiddleware())->toContain('throttle:5,1');
});
