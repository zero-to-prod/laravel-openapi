<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Server\Testing\TestResponse;
use ZeroToProd\LaravelOpenapi\Internal\Mcp\Server;
use ZeroToProd\LaravelOpenapi\Internal\Mcp\Tools\Document;
use ZeroToProd\LaravelOpenapi\Tests\Fixtures\ShowArticleController;

beforeEach(function (): void {
    $_ENV['TESTBENCH_WORKING_PATH'] = dirname(__DIR__, 2);
});

afterEach(function (): void {
    purgeBasePaths();
});

function document(array $arguments = []): TestResponse
{
    return Server::tool(Document::class, $arguments)->assertOk()->assertHasNoErrors();
}

function documentJson(array $arguments = []): mixed
{
    $text = mcpText(document($arguments));

    return json_decode(substr($text, (int) strpos($text, '{')), true);
}

it('describes the tool so an agent knows when to call it', function (): void {
    $tool = new Document;

    expect($tool->name())->toBe('document')
        ->and($tool->description())->toContain('OpenAPI document')
        ->and($tool->toArray()['inputSchema']['properties'])->toHaveKey('section');
});

it('returns the merged document as the JSON it is served as', function (): void {
    withoutFreshProcess();
    Route::get('articles/{id}', ShowArticleController::class);

    $decoded = documentJson();

    expect($decoded)
        ->toHaveKeys(['openapi', 'info', 'servers', 'paths'])
        ->and($decoded['paths'])->toHaveKey('/articles/{id}')
        ->and($decoded['paths']['/articles/{id}']['get']['operationId'])->toBe('showArticle');
});

it('reads the document from a fresh process rather than this one', function (): void {
    $this->withConfig(['openapi.route.uri' => 'changed.json']);

    document()
        ->assertSee('/openapi.json')
        ->assertDontSee(['/changed.json', '!! stale: no fresh process']);
});

it('marks the document stale when no fresh process can be started', function (): void {
    withoutFreshProcess();

    document()->assertSee([
        '!! stale: no fresh process',
        'php artisan openapi:inventory --document',
        '"openapi"',
    ]);
});

it('falls back rather than trusting a subprocess that printed something else', function (string $script): void {
    withArtisan($script);

    document()->assertSee('!! stale: no fresh process');
})->with([
    'a list where a document belongs' => '<?php echo json_encode(["one", "two"]);',
    'nothing parseable' => '<?php echo "Xdebug: something went wrong\n";',
    'a non-zero exit' => '<?php fwrite(STDERR, "boom"); exit(1);',
]);

it('returns one top-level key on request, and nothing else', function (): void {
    withoutFreshProcess();
    Route::get('articles/{id}', ShowArticleController::class);

    $decoded = documentJson(['section' => 'paths']);

    expect($decoded)->toHaveKey('/articles/{id}')
        ->and($decoded)->not->toHaveKey('openapi');
});

it('names the keys the document has when asked for one it does not', function (): void {
    withoutFreshProcess();

    document(['section' => 'nope'])
        ->assertSee(['The document declares no `nope`.', 'keys: openapi, info, servers, paths'])
        ->assertDontSee('{');
});

it('treats an empty section as no section at all', function (): void {
    withoutFreshProcess();

    document(['section' => ''])->assertSee('"openapi"');
});
