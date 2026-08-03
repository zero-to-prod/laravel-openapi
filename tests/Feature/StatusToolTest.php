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
    // Tells the Testbench skeleton's bootstrap where `vendor/` and
    // `testbench.yaml` live, so the process the tool spawns can boot the
    // package. `putenv()` alone would not reach the child: Symfony\Process
    // keeps only the `getenv()` values whose keys already exist in $_SERVER,
    // then merges $_ENV over the top.
    $_ENV['TESTBENCH_WORKING_PATH'] = dirname(__DIR__, 2);

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

/**
 * Points the application at the package root, which carries no `artisan`, so
 * the in-process fallback still runs but paths under it render relative.
 */
function withRepositoryAsBasePath(): void
{
    app()->setBasePath(dirname(__DIR__, 2));
}

/** An `artisan` printing the given inventory, and nothing else. */
function withInventory(array $entries): void
{
    withArtisan('<?php echo '.var_export(json_encode($entries, JSON_THROW_ON_ERROR), true).';');
}

/** The report as one string, for the assertions that are about its order. */
function statusText(array $arguments = []): string
{
    return mcpText(status($arguments));
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

it('names the project-local subclass, its file and a call site to read', function (): void {
    withRepositoryAsBasePath();
    Route::get('enum-constructed', EnumConstructedController::class);
    Route::post('articles/{id}/publish', UndocumentedController::class);

    status()->assertSee([
        '## Local convention',
        EnumConstructedSchema::class.' is the attribute this project uses',
        'on 1 of 2 documented routes, declared at tests/Fixtures/EnumConstructedSchema.php.',
        'Read that file and one call site — '.EnumConstructedController::class.'::__invoke — first.',
    ]);
});

it('tells the agent to add the subclass, not the package attribute it would find in `example`', function (): void {
    withoutFreshProcess();
    Route::get('enum-constructed', EnumConstructedController::class);
    Route::post('articles/{id}/publish', UndocumentedController::class);

    status()
        ->assertSee('Add a #[EnumConstructedSchema] attribute to each method below, following the local convention above.')
        ->assertDontSee('Call the `example` tool for the shape it takes.');
});

it('meets the agent with the convention before the work list, not after it', function (): void {
    withoutFreshProcess();
    Route::get('enum-constructed', EnumConstructedController::class);
    Route::post('articles/{id}/publish', UndocumentedController::class);

    $text = statusText();

    expect(strpos($text, '## Local convention'))->toBeLessThan((int) strpos($text, '## Undocumented routes'));
});

it('says so plainly when only the package attribute is in use', function (): void {
    withoutFreshProcess();
    Route::get('articles/{id}', ShowArticleController::class);

    status()
        ->assertSee([
            '## Local convention',
            "Documented routes in scope: 2, all using the package's #[ApiSchema] directly.",
            '{"topic": "attribute"}',
        ])
        ->assertDontSee('project-local');
});

it('lists every subclass with its count, and recommends none, when more than one is in use', function (): void {
    withRepositoryAsBasePath();
    Route::get('sub', SubclassSchemaController::class);
    Route::get('enum-constructed', EnumConstructedController::class);
    Route::post('articles/{id}/publish', UndocumentedController::class);

    status()
        ->assertSee([
            '## Local convention',
            'More than one attribute class is in use.',
            SubApiSchema::class.' — 1, e.g. '.SubclassSchemaController::class.'::__invoke (tests/Fixtures/SubApiSchema.php)',
            EnumConstructedSchema::class.' — 1, e.g. '.EnumConstructedController::class.'::__invoke',
            'Add an attribute to each method below, following the local convention above.',
        ])
        ->assertDontSee('is the attribute this project uses');
});

it('omits the convention section when nothing in scope is documented', function (): void {
    withoutFreshProcess();
    Route::post('api/messages', UndocumentedController::class);

    status(['path' => '/api'])
        ->assertDontSee('## Local convention')
        ->assertSee('Add an #[ApiSchema] attribute to each method below.');
});

it('reports an attribute class it cannot locate without a file pointer, rather than crashing', function (): void {
    withInventory([
        ['uri' => '/x', 'methods' => ['GET'], 'action' => 'A::b', 'documented' => true, 'attribute' => 'App\\Nope', 'schema' => []],
        ['uri' => '/y', 'methods' => ['GET'], 'action' => 'A::c', 'documented' => false, 'attribute' => null, 'schema' => []],
    ]);

    status()->assertSee([
        'App\Nope is the attribute this project uses: a project-local #[ApiSchema] subclass, on 1 of 1 documented routes.',
        'Read that class and one call site — A::b — first.',
        'Add a #[Nope] attribute to each method below',
    ]);
});

it('shows an absolute path for an attribute class declared outside the application', function (): void {
    withoutFreshProcess();
    Route::get('sub', SubclassSchemaController::class);

    status()->assertSee(', declared at '.dirname(__DIR__).'/Fixtures/SubApiSchema.php.');
});

it('keeps working against an inventory from a vendor copy that omits the attribute', function (): void {
    withInventory([
        ['uri' => '/legacy', 'methods' => ['GET'], 'action' => 'A::b', 'documented' => true, 'schema' => []],
    ]);

    status()
        ->assertSee('1 in scope, 1 documented, 0 undocumented')
        ->assertDontSee(['Could not read the application from a fresh process', '## Local convention']);
});

it('falls back rather than half-trusting an inventory whose attribute is not a class name', function (): void {
    withArtisan('<?php echo json_encode([["uri" => "/x", "methods" => ["GET"], "action" => "A::b", "documented" => true, "attribute" => 7, "schema" => []]]);');

    status()->assertSee('Could not read the application from a fresh process');
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
