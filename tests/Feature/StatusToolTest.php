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

afterEach(function (): void {
    SchemaCoverage::purge();

    foreach (glob(sys_get_temp_dir().'/openapi-status-base-*') ?: [] as $directory) {
        array_map(unlink(...), glob($directory.'/*') ?: []);
        rmdir($directory);
    }
});

function status(array $arguments = []): TestResponse
{
    return Server::tool(Status::class, $arguments)->assertOk()->assertHasNoErrors();
}

/**
 * Points the application at a base path holding the given `artisan`, or none at
 * all. The tool shells out to `artisan openapi:inventory` for a reading that a
 * long-lived process cannot give; every scenario below registers its routes in
 * *this* process, which a fresh one would never see, so they run against the
 * in-process fallback instead.
 */
function withArtisan(?string $script): string
{
    $directory = sys_get_temp_dir().'/openapi-status-base-'.bin2hex(random_bytes(6));

    mkdir($directory, 0755, true);

    if ($script !== null) {
        file_put_contents($directory.'/artisan', $script);
    }

    app()->setBasePath($directory);

    return $directory;
}

function withoutFreshProcess(): void
{
    withArtisan(null);
}

it('describes the tool so an agent knows when to call it', function (): void {
    $tool = new Status;

    expect($tool->name())->toBe('status')
        ->and($tool->description())->toContain('declare no schema')
        ->and($tool->toArray()['inputSchema']['properties'])->toHaveKey('path');
});

it('reads the application from a fresh process rather than this one', function (): void {
    // This process serves the document from /changed.json. A process starting
    // now reads the shipped default instead, so seeing /openapi.json — and not
    // /changed.json — is proof the inventory did not come from here.
    $this->withConfig([
        'openapi.route.uri' => 'changed.json',
        'openapi.coverage.path' => sys_get_temp_dir().'/openapi-status-test/coverage.jsonl',
    ]);

    status()
        ->assertSee('/openapi.json')
        ->assertDontSee('/changed.json')
        ->assertDontSee('Could not read the application from a fresh process');
});

it('warns that the reading is stale when no fresh process can be started', function (): void {
    withoutFreshProcess();

    status()->assertSee([
        'Could not read the application from a fresh process',
        'or edited since then are invisible here',
        'php artisan openapi:inventory',
    ]);
});

it('falls back rather than erroring when the fresh process exits non-zero', function (): void {
    withArtisan('<?php fwrite(STDERR, "boom"); exit(1);');

    status()->assertSee(['# Schema status', 'Could not read the application from a fresh process']);
});

it('falls back when the fresh process prints nothing it can parse', function (): void {
    withArtisan('<?php echo "Xdebug: something went wrong\n";');

    status()->assertSee('Could not read the application from a fresh process');
});

it('falls back rather than half-trusting an inventory of the wrong shape', function (): void {
    // A route reported with a numeric URI would render as undocumented work
    // that does not exist. Reject the batch instead of rendering part of it.
    withArtisan('<?php echo json_encode([["uri" => 123, "methods" => [], "documented" => false, "schema" => []]]);');

    status()->assertSee('Could not read the application from a fresh process');
});

it('falls back rather than half-trusting an inventory whose methods are not strings', function (): void {
    withArtisan('<?php echo json_encode([["uri" => "/x", "methods" => [7], "action" => "A::b", "documented" => false, "schema" => []]]);');

    status()->assertSee('Could not read the application from a fresh process');
});

it('ignores framework noise printed before the inventory', function (): void {
    withArtisan('<?php echo "Deprecated: something\n", json_encode([["uri" => "/only", "methods" => ["GET"], "action" => "A::b", "documented" => false, "schema" => []]]), "\n";');

    status()
        ->assertSee('GET /only — A::b')
        ->assertDontSee('Could not read the application from a fresh process');
});

it('names the routes that declare no schema, and where to add the attribute', function (): void {
    withoutFreshProcess();
    Route::post('articles/{id}/publish', UndocumentedController::class);

    status()->assertSee([
        '## Undocumented routes (1)',
        'POST /articles/{id}/publish — '.UndocumentedController::class.'::__invoke',
        'Call the `example` tool',
    ]);
});

it('counts a route carrying the attribute as documented', function (): void {
    withoutFreshProcess();
    Route::get('articles/{id}', ShowArticleController::class);

    // The package's own /openapi.json route is documented too, hence two.
    status()
        ->assertSee('2 documented, 0 undocumented')
        ->assertDontSee('## Undocumented routes');
});

it('reports declared responses that no test exercised', function (): void {
    withoutFreshProcess();
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
    withoutFreshProcess();
    Route::get('articles/{id}', ShowArticleController::class);

    status()
        ->assertSee(['No coverage is recorded at all', SchemaCoverage::path()])
        ->assertDontSee('assertMatchesSchema()');
});

it('re-reads coverage from disk, so a --reset between calls is visible', function (): void {
    withoutFreshProcess();
    Route::get('articles/{id}', ShowArticleController::class);
    SchemaCoverage::record('/articles/{id}', 'get', 200);

    status()->assertDontSee('GET /articles/{id} -> 200');

    SchemaCoverage::purge();

    status()->assertSee('GET /articles/{id} -> 200');
});

it('confirms the work is finished when nothing is outstanding', function (): void {
    withoutFreshProcess();
    SchemaCoverage::record('/openapi.json', 'get', 200);

    status(['path' => '/openapi.json'])->assertSee([
        'Scope: routes under /openapi.json.',
        'Every route in scope declares a schema, and every response it declares was exercised.',
    ]);
});

it('restricts the report to the given prefix, with or without a leading slash', function (string $prefix): void {
    withoutFreshProcess();
    Route::post('api/messages', UndocumentedController::class);

    status(['path' => $prefix])
        ->assertSee(['Routes: 1 in scope', 'POST /api/messages'])
        ->assertDontSee('/openapi.json');
})->with(['api', '/api', 'api/']);

it('reports every route when the prefix is only a slash', function (): void {
    withoutFreshProcess();

    status(['path' => '/'])
        ->assertDontSee('Scope: routes under')
        ->assertSee('/openapi.json');
});

it('says so when the prefix matches nothing, rather than reporting an empty success', function (): void {
    withoutFreshProcess();

    status(['path' => '/nope'])
        ->assertSee('No registered route starts with /nope')
        ->assertDontSee('Every route in scope');
});

it('says so when no routes are registered at all', function (): void {
    $this->withConfig([
        'openapi.route.enabled' => false,
        'openapi.coverage.path' => sys_get_temp_dir().'/openapi-status-test/coverage.jsonl',
    ]);
    withoutFreshProcess();

    status()->assertSee('No routes are registered at all');
});

it('separates closure routes, which cannot carry an attribute', function (): void {
    withoutFreshProcess();
    Route::get('health', fn (): string => 'ok');

    status()->assertSee([
        '## Routes that cannot be documented (1)',
        'GET /health',
        'Move each to a controller method',
    ]);
});

it('does not claim missing coverage when nothing in scope declares a response', function (): void {
    withoutFreshProcess();
    Route::post('api/messages', UndocumentedController::class);

    status(['path' => '/api'])
        ->assertSee([
            'Responses: 0 declared, 0 never exercised.',
            'Nothing in scope declares a response yet',
        ])
        ->assertDontSee('Every route in scope');
});
