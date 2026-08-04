<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Server\Testing\TestResponse;
use ZeroToProd\LaravelOpenapi\Internal\Mcp\Server;
use ZeroToProd\LaravelOpenapi\Internal\Mcp\Tools\Endpoint;
use ZeroToProd\LaravelOpenapi\Internal\SchemaCoverage;
use ZeroToProd\LaravelOpenapi\Tests\Fixtures\ComponentReferenceController;
use ZeroToProd\LaravelOpenapi\Tests\Fixtures\DanglingReferenceController;
use ZeroToProd\LaravelOpenapi\Tests\Fixtures\PathLevelParametersController;
use ZeroToProd\LaravelOpenapi\Tests\Fixtures\ShowArticleController;
use ZeroToProd\LaravelOpenapi\Tests\Fixtures\UndocumentedController;

beforeEach(function (): void {
    $_ENV['TESTBENCH_WORKING_PATH'] = dirname(__DIR__, 2);

    config(['openapi.coverage.path' => sys_get_temp_dir().'/openapi-endpoint-test/coverage.jsonl']);
    SchemaCoverage::purge();
});

afterEach(function (): void {
    SchemaCoverage::purge();
    purgeBasePaths();
});

function endpoint(array $arguments): TestResponse
{
    return Server::tool(Endpoint::class, $arguments)->assertOk();
}

function endpointText(array $arguments): string
{
    return mcpText(endpoint($arguments));
}

it('describes the tool so an agent knows when to call it', function (): void {
    $tool = new Endpoint;
    $schema = $tool->toArray()['inputSchema'];

    expect($tool->name())->toBe('endpoint')
        ->and($tool->description())->toContain('one endpoint')
        ->and($schema['properties'])->toHaveKeys(['path', 'method'])
        ->and($schema['required'])->toBe(['path']);
});

it('errors rather than guessing when no path is given', function (mixed $path): void {
    Server::tool(Endpoint::class, $path === null ? [] : ['path' => $path])->assertHasErrors();
})->with([null, '', '/']);

it('reports the route, its middleware and the attribute carrying the schema', function (): void {
    withoutFreshProcess();
    Route::middleware('api')->get('articles/{id}', ShowArticleController::class);

    endpoint(['path' => '/articles/{id}'])->assertSee([
        '# /articles/{id}',
        'route: GET /articles/{id} — '.ShowArticleController::class.'::__invoke [api]',
        'attribute: ZeroToProd\LaravelOpenapi\ApiSchema',
        '## Declared',
        '"operationId": "showArticle"',
    ]);
});

it('says which of the declared responses a test has exercised', function (): void {
    withoutFreshProcess();
    Route::get('articles/{id}', ShowArticleController::class);
    SchemaCoverage::record('/articles/{id}', 'get', 200);

    endpoint(['path' => 'articles/{id}'])->assertSee([
        'responses: 2 declared, 1 unexercised',
        '- GET /articles/{id} -> 200 exercised',
        '- GET /articles/{id} -> 404 unexercised',
    ]);
});

it('says the suite has not run rather than blaming the endpoint, when nothing is recorded', function (): void {
    withoutFreshProcess();
    Route::get('articles/{id}', ShowArticleController::class);

    endpoint(['path' => '/articles/{id}'])
        ->assertSee('no coverage recorded at all, so nothing counts as exercised');
});

it('narrows the declaration to one method when given one', function (): void {
    withInventory([[
        'uri' => '/articles', 'methods' => ['GET', 'POST'], 'action' => 'A::b', 'middleware' => [], 'documented' => true,
        'attribute' => 'App\\Schema', 'schema' => ['paths' => ['/articles' => [
            'get' => ['operationId' => 'index', 'responses' => ['200' => ['description' => 'Many.']]],
            'post' => ['operationId' => 'store', 'responses' => ['201' => ['description' => 'One.']]],
        ]]],
    ]]);

    endpoint(['path' => '/articles', 'method' => 'post'])
        ->assertSee(['# POST /articles', '"operationId": "store"'])
        ->assertDontSee('"operationId": "index"');
});

it('keeps the path-level keys that apply to the method it narrowed to', function (): void {
    withoutFreshProcess();
    Route::get('path-level-parameters', PathLevelParametersController::class);

    endpoint(['path' => '/path-level-parameters', 'method' => 'GET'])->assertSee([
        '"parameters"',
        '"name": "trace"',
        '"operationId": "pathLevelParameters"',
    ]);
});

it('reports a method the route serves but the attribute never declared', function (): void {
    withInventory([[
        'uri' => '/articles', 'methods' => ['GET', 'POST'], 'action' => 'A::b', 'middleware' => [], 'documented' => true,
        'attribute' => 'App\\Schema', 'schema' => ['paths' => ['/articles' => [
            'parameters' => [['name' => 'trace', 'in' => 'query']],
            'get' => ['operationId' => 'index', 'responses' => ['200' => ['description' => 'Many.']]],
        ]]],
    ]]);

    endpoint(['path' => '/articles', 'method' => 'POST'])
        ->assertSee(['route: GET|POST /articles — A::b', 'responses: none declared'])
        ->assertDontSee(['## Declared', '"operationId"']);
});

it('resolves the components the declaration reaches, including through another', function (): void {
    withoutFreshProcess();
    Route::get('component-reference', ComponentReferenceController::class);

    $text = endpointText(['path' => '/component-reference']);

    expect($text)
        ->toContain('## Components it references')
        ->toContain('"Article"')
        ->toContain('"Author"')
        ->not->toContain('"Unused"');
});

it('leaves out a reference that resolves to nothing, rather than inventing it', function (): void {
    withoutFreshProcess();
    Route::get('dangling-reference', DanglingReferenceController::class);

    endpoint(['path' => '/dangling-reference'])
        ->assertSee('"$ref": "#/components/schemas/DoesNotExist"')
        ->assertDontSee('## Components it references');
});

it('reads components declared by another route, since they merge across attributes', function (): void {
    withInventory([
        ['uri' => '/holder', 'methods' => ['GET'], 'action' => 'A::holder', 'middleware' => [], 'documented' => true, 'attribute' => 'App\\Schema',
            'schema' => ['components' => ['schemas' => ['Shared' => ['type' => 'string']]]]],
        ['uri' => '/user', 'methods' => ['GET'], 'action' => 'A::user', 'middleware' => [], 'documented' => true, 'attribute' => 'App\\Schema',
            'schema' => ['paths' => ['/user' => ['get' => ['responses' => ['200' => ['content' => ['application/json' => [
                'schema' => ['$ref' => '#/components/schemas/Shared'],
            ]]]]]]]]],
    ]);

    endpoint(['path' => '/user'])->assertSee(['## Components it references', '"Shared"']);
});

it('finds an endpoint by the path it declares, not only by the URI it is routed at', function (): void {
    withInventory([[
        'uri' => '/api/articles', 'methods' => ['GET'], 'action' => 'A::b', 'middleware' => ['api'], 'documented' => true,
        'attribute' => 'App\\Schema', 'schema' => ['paths' => ['/articles' => [
            'get' => ['operationId' => 'index', 'responses' => ['200' => ['description' => 'Many.']]],
        ]]],
    ]]);

    endpoint(['path' => '/articles'])->assertSee([
        '# /articles',
        'route: GET /api/articles — A::b [api]',
        '"operationId": "index"',
    ]);
});

it('matches a method the attribute declares even when the route does not serve it', function (): void {
    withInventory([[
        'uri' => '/articles', 'methods' => ['GET'], 'action' => 'A::b', 'middleware' => [], 'documented' => true,
        'attribute' => 'App\\Schema', 'schema' => ['paths' => ['/articles' => [
            'post' => ['operationId' => 'store', 'responses' => ['201' => ['description' => 'One.']]],
        ]]],
    ]]);

    endpoint(['path' => '/articles', 'method' => 'POST'])->assertSee([
        'route: GET /articles — A::b',
        '"operationId": "store"',
    ]);
});

it('reports a route that declares nothing as declaring nothing', function (): void {
    withoutFreshProcess();
    Route::post('articles/{id}/publish', UndocumentedController::class);

    endpoint(['path' => '/articles/{id}/publish'])
        ->assertSee([
            'route: POST /articles/{id}/publish — '.UndocumentedController::class.'::__invoke',
            'attribute: none, so this route declares nothing',
            'responses: none declared',
        ])
        ->assertDontSee(['## Declared', '['.']']);
});

it('reports a closure route as one that cannot carry an attribute', function (): void {
    withoutFreshProcess();
    Route::get('health', fn (): string => 'ok');

    endpoint(['path' => '/health'])->assertSee('route: GET /health — closure, which cannot carry an attribute');
});

it('names the methods the path does serve when the one asked for is not among them', function (): void {
    withoutFreshProcess();
    Route::get('articles/{id}', ShowArticleController::class);

    endpoint(['path' => '/articles/{id}', 'method' => 'delete'])
        ->assertSee('DELETE is not served here. Methods on /articles/{id}: GET.')
        ->assertDontSee('## Declared');
});

it('offers the URIs containing what was asked for when nothing matches it', function (): void {
    withoutFreshProcess();
    Route::get('articles/{id}', ShowArticleController::class);

    endpoint(['path' => '/articles'])->assertSee([
        'No route and no declared path matches /articles.',
        'URIs containing it:',
        '- GET /articles/{id}',
    ]);
});

it('says only that nothing matches when nothing comes close', function (): void {
    withoutFreshProcess();

    endpoint(['path' => '/nope'])
        ->assertSee('No route and no declared path matches /nope.')
        ->assertDontSee('URIs containing it');
});

it('reads the application from a fresh process rather than this one', function (): void {
    $this->withConfig([
        'openapi.route.uri' => 'changed.json',
        'openapi.coverage.path' => sys_get_temp_dir().'/openapi-endpoint-test/coverage.jsonl',
    ]);

    endpoint(['path' => '/openapi.json'])
        ->assertSee('route: GET /openapi.json — ')
        ->assertDontSee('!! stale: no fresh process');
});

it('marks the reading stale when no fresh process can be started', function (): void {
    withoutFreshProcess();
    Route::get('articles/{id}', ShowArticleController::class);

    endpoint(['path' => '/articles/{id}'])->assertSee('!! stale: no fresh process');
});

it('reports only the path asked for, when one attribute declares several', function (): void {
    withInventory([[
        'uri' => '/articles', 'methods' => ['GET'], 'action' => 'A::b', 'middleware' => [], 'documented' => true,
        'attribute' => 'App\\Schema', 'schema' => ['paths' => [
            '/articles' => ['get' => ['operationId' => 'index', 'responses' => ['200' => ['description' => 'Many.']]]],
            '/articles/{id}' => ['get' => ['operationId' => 'show', 'responses' => ['200' => ['description' => 'One.']]]],
        ]],
    ]]);

    endpoint(['path' => '/articles'])
        ->assertSee('"operationId": "index"')
        ->assertDontSee(['"operationId": "show"', '/articles/{id}']);
});

it('reports a component reached twice once, rather than chasing it twice', function (): void {
    withInventory([[
        'uri' => '/articles', 'methods' => ['GET'], 'action' => 'A::b', 'middleware' => [], 'documented' => true,
        'attribute' => 'App\\Schema', 'schema' => [
            'components' => ['schemas' => ['Article' => ['type' => 'object']]],
            'paths' => ['/articles' => ['get' => ['responses' => [
                '200' => ['content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Article']]]],
                '201' => ['content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Article']]]],
            ]]]],
        ],
    ]]);

    $text = endpointText(['path' => '/articles']);

    expect(substr_count($text, '"Article": {'))->toBe(1);
});

it('reports the route registered for the method, not its neighbour sharing the URI', function (): void {
    $paths = ['/articles' => [
        'get' => ['operationId' => 'index', 'responses' => ['200' => ['description' => 'Many.']]],
        'post' => ['operationId' => 'store', 'responses' => ['201' => ['description' => 'One.']]],
    ]];

    // One fragment declaring both methods, carried by both routes — which is
    // what an attribute keyed by path rather than by operation produces.
    withInventory([
        ['uri' => '/articles', 'methods' => ['GET'], 'action' => 'A::index', 'middleware' => [], 'documented' => true, 'attribute' => 'App\\Schema', 'schema' => ['paths' => $paths]],
        ['uri' => '/articles', 'methods' => ['POST'], 'action' => 'A::store', 'middleware' => [], 'documented' => true, 'attribute' => 'App\\Schema', 'schema' => ['paths' => $paths]],
    ]);

    endpoint(['path' => '/articles', 'method' => 'POST'])
        ->assertSee('— A::store')
        ->assertDontSee('— A::index');
});

it('reports one declaration once, with every route it covers', function (): void {
    $paths = ['/articles' => [
        'get' => ['operationId' => 'index', 'responses' => ['200' => ['description' => 'Many.']]],
        'post' => ['operationId' => 'store', 'responses' => ['201' => ['description' => 'One.']]],
    ]];

    withInventory([
        ['uri' => '/articles', 'methods' => ['GET'], 'action' => 'A::index', 'middleware' => [], 'documented' => true, 'attribute' => 'App\\Schema', 'schema' => ['paths' => $paths]],
        ['uri' => '/articles', 'methods' => ['POST'], 'action' => 'A::store', 'middleware' => [], 'documented' => true, 'attribute' => 'App\\Schema', 'schema' => ['paths' => $paths]],
    ]);

    $text = endpointText(['path' => '/articles']);

    expect($text)
        ->toContain('route: GET /articles — A::index')
        ->toContain('route: POST /articles — A::store')
        ->and(substr_count($text, '## Declared'))->toBe(1)
        ->and(substr_count($text, '"operationId": "index"'))->toBe(1);
});

it('keeps declarations apart when the routes at a URI do not share one', function (): void {
    withInventory([
        ['uri' => '/articles', 'methods' => ['GET'], 'action' => 'A::index', 'middleware' => [], 'documented' => true, 'attribute' => 'App\\Schema',
            'schema' => ['paths' => ['/articles' => ['get' => ['operationId' => 'index', 'responses' => ['200' => ['description' => 'Many.']]]]]]],
        ['uri' => '/articles', 'methods' => ['POST'], 'action' => 'A::store', 'middleware' => [], 'documented' => true, 'attribute' => 'App\\Schema',
            'schema' => ['paths' => ['/articles' => ['post' => ['operationId' => 'store', 'responses' => ['201' => ['description' => 'One.']]]]]]],
    ]);

    $text = endpointText(['path' => '/articles']);

    expect(substr_count($text, '## Declared'))->toBe(2)
        ->and($text)->toContain('"operationId": "index"')
        ->and($text)->toContain('"operationId": "store"');
});
