<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Server\Testing\TestResponse;
use ZeroToProd\LaravelOpenapi\Internal\Mcp\Server;
use ZeroToProd\LaravelOpenapi\Internal\Mcp\Tools\Status;
use ZeroToProd\LaravelOpenapi\Internal\SchemaCoverage;
use ZeroToProd\LaravelOpenapi\Tests\Fixtures\EnumConstructedController;
use ZeroToProd\LaravelOpenapi\Tests\Fixtures\EnumConstructedSchema;
use ZeroToProd\LaravelOpenapi\Tests\Fixtures\ShowArticleController;
use ZeroToProd\LaravelOpenapi\Tests\Fixtures\SubApiSchema;
use ZeroToProd\LaravelOpenapi\Tests\Fixtures\SubclassSchemaController;
use ZeroToProd\LaravelOpenapi\Tests\Fixtures\UndocumentedController;

beforeEach(function (): void {
    $_ENV['TESTBENCH_WORKING_PATH'] = dirname(__DIR__, 2);

    config(['openapi.coverage.path' => sys_get_temp_dir().'/openapi-status-test/coverage.jsonl']);
    SchemaCoverage::purge();
});

afterEach(function (): void {
    SchemaCoverage::purge();
    purgeBasePaths();
});

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

it('reads the application from a fresh process rather than this one', function (): void {
    $this->withConfig([
        'openapi.route.uri' => 'changed.json',
        'openapi.coverage.path' => sys_get_temp_dir().'/openapi-status-test/coverage.jsonl',
    ]);

    status()
        ->assertSee('/openapi.json')
        ->assertDontSee('/changed.json')
        ->assertDontSee('!! stale: no fresh process');
});

it('warns that the reading is stale when no fresh process can be started', function (): void {
    withoutFreshProcess();

    status()->assertSee([
        '!! stale: no fresh process',
        'php artisan openapi:inventory',
    ]);
});

it('falls back rather than erroring when the fresh process exits non-zero', function (): void {
    withArtisan('<?php fwrite(STDERR, "boom"); exit(1);');

    status()->assertSee(['# Schema status', '!! stale: no fresh process']);
});

it('falls back when the fresh process prints nothing it can parse', function (): void {
    withArtisan('<?php echo "Xdebug: something went wrong\n";');

    status()->assertSee('!! stale: no fresh process');
});

it('falls back rather than half-trusting an inventory of the wrong shape', function (): void {
    withArtisan('<?php echo json_encode([["uri" => 123, "methods" => [], "documented" => false, "schema" => []]]);');

    status()->assertSee('!! stale: no fresh process');
});

it('falls back rather than half-trusting an inventory whose methods are not strings', function (): void {
    withArtisan('<?php echo json_encode([["uri" => "/x", "methods" => [7], "action" => "A::b", "documented" => false, "schema" => []]]);');

    status()->assertSee('!! stale: no fresh process');
});

it('ignores framework noise printed before the inventory', function (): void {
    withArtisan('<?php echo "Deprecated: something\n", json_encode([["uri" => "/only", "methods" => ["GET"], "action" => "A::b", "documented" => false, "schema" => []]]), "\n";');

    status()
        ->assertSee('GET /only — A::b')
        ->assertDontSee('!! stale: no fresh process');
});

it('names the routes that declare no schema', function (): void {
    withoutFreshProcess();
    Route::post('articles/{id}/publish', UndocumentedController::class);

    status()
        ->assertSee([
            '## Undocumented routes (1)',
            'POST /articles/{id}/publish — '.UndocumentedController::class.'::__invoke',
        ])
        ->assertDontSee(['Add a', 'Call the `example` tool']);
});

it('reports the middleware a route runs, without telling the agent what to make of it', function (): void {
    withoutFreshProcess();
    Route::middleware(['auth:sanctum', 'App\Http\Middleware\CheckForAnyAbility:user'])
        ->post('articles/{id}/publish', UndocumentedController::class);

    status()
        ->assertSee(
            'POST /articles/{id}/publish — '.UndocumentedController::class
            .'::__invoke [auth:sanctum, CheckForAnyAbility:user]'
        )
        ->assertDontSee(['Middleware decides which statuses', "->withToken('any-value')"]);
});

it('says nothing about middleware for a route that runs none', function (): void {
    withoutFreshProcess();
    Route::post('articles/{id}/publish', UndocumentedController::class);

    status()
        ->assertSee('POST /articles/{id}/publish — '.UndocumentedController::class.'::__invoke')
        ->assertDontSee(UndocumentedController::class.'::__invoke [');
});

it('falls back rather than half-trusting an inventory whose middleware is not a list of strings', function (string $middleware): void {
    withArtisan(sprintf(
        '<?php echo json_encode([["uri" => "/x", "methods" => ["GET"], "action" => "A::b", "middleware" => %s, "documented" => false, "schema" => []]]);',
        $middleware,
    ));

    status()->assertSee('!! stale: no fresh process');
})->with(['[7]', '"auth"']);

it('counts the attribute classes the documented routes carry, and recommends none of them', function (): void {
    withRepositoryAsBasePath();
    Route::get('sub', SubclassSchemaController::class);
    Route::get('enum-constructed', EnumConstructedController::class);
    Route::post('articles/{id}/publish', UndocumentedController::class);

    status()
        ->assertSee('attributes in use: ')
        ->assertSee(SubApiSchema::class.' (1)')
        ->assertSee(EnumConstructedSchema::class.' (1)')
        ->assertDontSee(['## Local convention', 'this project uses', 'One entry to copy', 'Follow it']);
});

it('orders the attribute classes by how many routes carry each', function (): void {
    withInventory([
        ['uri' => '/read', 'methods' => ['GET'], 'action' => 'A::read', 'middleware' => [], 'documented' => true, 'attribute' => 'App\\Rare', 'schema' => []],
        ['uri' => '/write', 'methods' => ['POST'], 'action' => 'A::write', 'middleware' => [], 'documented' => true, 'attribute' => 'App\\Common', 'schema' => []],
        ['uri' => '/patch', 'methods' => ['PATCH'], 'action' => 'A::patch', 'middleware' => [], 'documented' => true, 'attribute' => 'App\\Common', 'schema' => []],
    ]);

    status()->assertSee('attributes in use: App\Common (2), App\Rare (1)');
});

it('counts no attribute for a route that declares no schema', function (): void {
    withoutFreshProcess();
    Route::post('api/messages', UndocumentedController::class);

    status(['path' => '/api'])->assertDontSee('attributes in use');
});

it('keeps working against an inventory from a vendor copy that omits the attribute', function (): void {
    withInventory([
        ['uri' => '/legacy', 'methods' => ['GET'], 'action' => 'A::b', 'documented' => true, 'schema' => []],
    ]);

    status()
        ->assertSee('routes: 1, documented 1, undocumented 0, closure 0')
        ->assertDontSee(['!! stale: no fresh process', 'attributes in use']);
});

it('falls back rather than half-trusting an inventory whose attribute is not a class name', function (): void {
    withArtisan('<?php echo json_encode([["uri" => "/x", "methods" => ["GET"], "action" => "A::b", "documented" => true, "attribute" => 7, "schema" => []]]);');

    status()->assertSee('!! stale: no fresh process');
});

it('counts a route carrying the attribute as documented', function (): void {
    withoutFreshProcess();
    Route::get('articles/{id}', ShowArticleController::class);

    status()
        ->assertSee('documented 2, undocumented 0')
        ->assertDontSee('## Undocumented routes');
});

it('reports declared responses that no test exercised', function (): void {
    withoutFreshProcess();
    Route::get('articles/{id}', ShowArticleController::class);
    SchemaCoverage::record('/articles/{id}', 'get', 200);

    status()
        ->assertSee([
            '## Declared responses no test exercised (2)',
            'GET /articles/{id} -> 404',
            'GET /openapi.json -> 200',
        ])
        ->assertDontSee(['GET /articles/{id} -> 200', 'assertMatchesSchema()']);
});

it('says the suite has not run rather than blaming the tests, when nothing is recorded', function (): void {
    withoutFreshProcess();
    Route::get('articles/{id}', ShowArticleController::class);

    status()->assertSee(['no coverage recorded at all', SchemaCoverage::path()]);
});

it('re-reads coverage from disk, so a --reset between calls is visible', function (): void {
    withoutFreshProcess();
    Route::get('articles/{id}', ShowArticleController::class);
    SchemaCoverage::record('/articles/{id}', 'get', 200);

    status()->assertDontSee('GET /articles/{id} -> 200');

    SchemaCoverage::purge();

    status()->assertSee('GET /articles/{id} -> 200');
});

it('reports zero outstanding work as counts, and adds nothing to them', function (): void {
    withoutFreshProcess();
    SchemaCoverage::record('/openapi.json', 'get', 200);

    status(['path' => '/openapi.json'])
        ->assertSee([
            'scope: /openapi.json',
            'routes: 1, documented 1, undocumented 0, closure 0',
            'responses: 1 declared, 0 unexercised',
        ])
        ->assertDontSee(['## Undocumented routes', '## Declared responses']);
});

it('restricts the report to the given prefix, with or without a leading slash', function (string $prefix): void {
    withoutFreshProcess();
    Route::post('api/messages', UndocumentedController::class);

    status(['path' => $prefix])
        ->assertSee(['routes: 1,', 'POST /api/messages'])
        ->assertDontSee('/openapi.json');
})->with(['api', '/api', 'api/']);

it('reports every route when the prefix is only a slash', function (): void {
    withoutFreshProcess();

    status(['path' => '/'])
        ->assertDontSee('scope:')
        ->assertSee('/openapi.json');
});

it('says so when the prefix matches nothing, rather than reporting an empty success', function (): void {
    withoutFreshProcess();

    status(['path' => '/nope'])
        ->assertSee('routes: 0 matching /nope.')
        ->assertDontSee('## Undocumented routes');
});

it('says so when no routes are registered at all', function (): void {
    $this->withConfig([
        'openapi.route.enabled' => false,
        'openapi.coverage.path' => sys_get_temp_dir().'/openapi-status-test/coverage.jsonl',
    ]);
    withoutFreshProcess();

    status()->assertSee('routes: 0 registered.');
});

it('separates closure routes, which cannot carry an attribute', function (): void {
    withoutFreshProcess();
    Route::get('health', fn (): string => 'ok');

    status()->assertSee([
        '## Closure routes (1) — cannot carry an attribute',
        'GET /health',
    ]);
});

it('does not claim missing coverage when nothing in scope declares a response', function (): void {
    withoutFreshProcess();
    Route::post('api/messages', UndocumentedController::class);

    status(['path' => '/api'])
        ->assertSee('responses: 0 declared, 0 unexercised')
        ->assertDontSee('## Declared responses');
});
