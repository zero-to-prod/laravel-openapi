<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Server\Testing\TestResponse;
use ZeroToProd\LaravelOpenapi\Internal\Mcp\Server;
use ZeroToProd\LaravelOpenapi\Internal\Mcp\Tools\Status;
use ZeroToProd\LaravelOpenapi\Internal\SchemaCoverage;
use ZeroToProd\LaravelOpenapi\Tests\Fixtures\ShowArticleController;
use ZeroToProd\LaravelOpenapi\Tests\Fixtures\UndocumentedController;

beforeEach(function (): void {
    config(['openapi.coverage.path' => sys_get_temp_dir().'/openapi-status-test/coverage.jsonl']);
    SchemaCoverage::purge();
});

afterEach(fn () => SchemaCoverage::purge());

function status(array $arguments = []): TestResponse
{
    return Server::tool(Status::class, $arguments)->assertOk()->assertHasNoErrors();
}

it('describes the tool so an agent knows when to call it', function (): void {
    $tool = new Status;

    expect($tool->name())->toBe('status')
        ->and($tool->description())->toContain('declare no schema')
        ->and($tool->toArray()['inputSchema']['properties'])->toHaveKey('path');
});

it('names the routes that declare no schema, and where to add the attribute', function (): void {
    Route::post('articles/{id}/publish', UndocumentedController::class);

    status()->assertSee([
        '## Undocumented routes (1)',
        'POST /articles/{id}/publish — '.UndocumentedController::class.'::__invoke',
        'Call the `example` tool',
    ]);
});

it('counts a route carrying the attribute as documented', function (): void {
    Route::get('articles/{id}', ShowArticleController::class);

    // The package's own /openapi.json route is documented too, hence two.
    status()
        ->assertSee('2 documented, 0 undocumented')
        ->assertDontSee('## Undocumented routes');
});

it('reports declared responses that no test exercised', function (): void {
    Route::get('articles/{id}', ShowArticleController::class);
    SchemaCoverage::record('/articles/{id}', 'get', 200);

    status()
        ->assertSee([
            '## Declared responses no test exercised',
            'GET /articles/{id} -> 404',
            'assertMatchesSchema()',
        ])
        ->assertDontSee('GET /articles/{id} -> 200');
});

it('says the suite has not run rather than blaming the tests, when nothing is recorded', function (): void {
    Route::get('articles/{id}', ShowArticleController::class);

    status()
        ->assertSee(['No coverage is recorded at all', SchemaCoverage::path()])
        ->assertDontSee('assertMatchesSchema()');
});

it('confirms the work is finished when nothing is outstanding', function (): void {
    SchemaCoverage::record('/openapi.json', 'get', 200);

    status(['path' => '/openapi.json'])->assertSee([
        'Scope: routes under /openapi.json.',
        'Every route in scope declares a schema, and every response it declares was exercised.',
    ]);
});

it('restricts the report to the given prefix, with or without a leading slash', function (string $prefix): void {
    Route::post('api/messages', UndocumentedController::class);

    status(['path' => $prefix])
        ->assertSee(['Routes: 1 in scope', 'POST /api/messages'])
        ->assertDontSee('/openapi.json');
})->with(['api', '/api', 'api/']);

it('reports every route when the prefix is only a slash', function (): void {
    status(['path' => '/'])
        ->assertDontSee('Scope: routes under')
        ->assertSee('/openapi.json');
});

it('says so when the prefix matches nothing, rather than reporting an empty success', function (): void {
    status(['path' => '/nope'])
        ->assertSee('No registered route starts with /nope')
        ->assertDontSee('Every route in scope');
});

it('says so when no routes are registered at all', function (): void {
    $this->withConfig([
        'openapi.route.enabled' => false,
        'openapi.coverage.path' => sys_get_temp_dir().'/openapi-status-test/coverage.jsonl',
    ]);

    status()->assertSee('No routes are registered at all');
});

it('separates closure routes, which cannot carry an attribute', function (): void {
    Route::get('health', fn (): string => 'ok');

    status()->assertSee([
        '## Routes that cannot be documented (1)',
        'GET /health',
        'Move each to a controller method',
    ]);
});

it('does not claim missing coverage when nothing in scope declares a response', function (): void {
    Route::post('api/messages', UndocumentedController::class);

    status(['path' => '/api'])
        ->assertSee([
            'Responses: 0 declared, 0 never exercised.',
            'Nothing in scope declares a response yet',
        ])
        ->assertDontSee('Every route in scope');
});
