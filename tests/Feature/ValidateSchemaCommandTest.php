<?php

declare(strict_types=1);

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use ZeroToProd\LaravelOpenapi\SchemaGenerator;
use ZeroToProd\LaravelOpenapi\Tests\Fixtures\DanglingReferenceController;
use ZeroToProd\LaravelOpenapi\Tests\Fixtures\DeclaredSecuritySchemeController;
use ZeroToProd\LaravelOpenapi\Tests\Fixtures\MalformedDocumentGenerator;
use ZeroToProd\LaravelOpenapi\Tests\Fixtures\MissingResponsesController;
use ZeroToProd\LaravelOpenapi\Tests\Fixtures\MultiPathController;
use ZeroToProd\LaravelOpenapi\Tests\Fixtures\PathLevelParametersController;
use ZeroToProd\LaravelOpenapi\Tests\Fixtures\SecondUndeclaredSecurityController;
use ZeroToProd\LaravelOpenapi\Tests\Fixtures\ShowArticleController;
use ZeroToProd\LaravelOpenapi\Tests\Fixtures\StringPathsGenerator;
use ZeroToProd\LaravelOpenapi\Tests\Fixtures\UndeclaredSecuritySchemeController;
use ZeroToProd\LaravelOpenapi\Tests\Fixtures\UndocumentedController;

function validateSchema(): array
{
    return [Artisan::call('openapi:validate'), Artisan::output()];
}

it('passes for the document the package generates', function (): void {
    [$status, $output] = validateSchema();

    expect($status)->toBe(0)
        ->and($output)->toContain('valid OpenAPI 3.0.4 document (1 paths)');
});

it('fails when an operation omits a required field', function (): void {
    Route::get('missing-responses', MissingResponsesController::class);

    [$status, $output] = validateSchema();

    expect($status)->toBe(1)
        ->and($output)->toContain('Operation is missing required property: responses');
});

it('fails when a reference does not resolve', function (): void {
    Route::get('dangling-reference', DanglingReferenceController::class);

    [$status, $output] = validateSchema();

    expect($status)->toBe(1)
        ->and($output)->toContain('#/components/schemas/DoesNotExist');
});

it('fails when the generated document cannot be read', function (): void {
    app()->instance(SchemaGenerator::class, new SchemaGenerator(
        app(Router::class),
        ['openapi' => "\xB1\x31"],
    ));

    [$status, $output] = validateSchema();

    expect($status)->toBe(1)
        ->and($output)->toContain('could not be read');
});

it('reports every error at once rather than stopping at the first', function (): void {
    Route::get('missing-responses', MissingResponsesController::class);
    Route::get('dangling-reference', DanglingReferenceController::class);

    [$status, $output] = validateSchema();

    expect($status)->toBe(1)
        ->and($output)->toContain('#/components/schemas/DoesNotExist')
        ->and($output)->toContain('Operation is missing required property: responses');
});

it('fails when an operation requires a security scheme nothing declares', function (): void {
    Route::get('undeclared-security', UndeclaredSecuritySchemeController::class);

    [$status, $output] = validateSchema();

    expect($status)->toBe(1)
        ->and($output)->toContain("Mentioned security scheme 'sanctum' not found in the given spec")
        ->and($output)->toContain('referenced by get /undeclared-security');
});

it('passes when the scheme is declared, and ignores the optional-auth idiom', function (): void {
    Route::get('declared-security', DeclaredSecuritySchemeController::class);

    [$status, $output] = validateSchema();

    expect($status)->toBe(0)
        ->and($output)->toContain('valid OpenAPI 3.0.4 document (2 paths)');
});

it('fails when a document-level security requirement names nothing declared', function (): void {
    app()->instance(SchemaGenerator::class, new SchemaGenerator(
        app(Router::class),
        [...config('openapi.openapi'), 'security' => [['oauth' => []]]],
    ));

    [$status, $output] = validateSchema();

    expect($status)->toBe(1)
        ->and($output)->toContain("Mentioned security scheme 'oauth' not found in the given spec")
        ->and($output)->toContain('referenced by the document-level security requirement');
});

it('names every operation that references a missing scheme, not just the first', function (): void {
    Route::get('undeclared-security', UndeclaredSecuritySchemeController::class);
    Route::get('also-undeclared', SecondUndeclaredSecurityController::class);

    [$status, $output] = validateSchema();

    expect($status)->toBe(1)
        ->and($output)->toContain('referenced by get /undeclared-security')
        ->and($output)->toContain('referenced by post /also-undeclared');
});

it('survives a path item and a security entry that are the wrong type entirely', function (): void {
    app()->instance(SchemaGenerator::class, new MalformedDocumentGenerator(app(Router::class)));

    [$status, $output] = validateSchema();

    expect($status)->toBe(1)
        ->and($output)->toContain('could not be read')
        ->and($output)->not->toContain("Mentioned security scheme 'not-a-requirement-object'");
});

it('survives a document whose paths is not an object at all', function (): void {
    app()->instance(SchemaGenerator::class, new StringPathsGenerator(app(Router::class)));

    [$status, $output] = validateSchema();

    expect($status)->toBe(1)->and($output)->not->toBe('');
});

it('does not mistake a path item parameters key for an operation', function (): void {
    Route::get('path-level-parameters', PathLevelParametersController::class);

    [$status, $output] = validateSchema();

    expect($status)->toBe(0)
        ->and($output)->toContain('valid OpenAPI 3.0.4 document');
});

it('fails when the declared path renames a route placeholder', function (): void {
    Route::get('articles/{article_id}', ShowArticleController::class);

    [$status, $output] = validateSchema();

    expect($status)->toBe(1)
        ->and($output)->toContain('The attribute on '.ShowArticleController::class.'::__invoke')
        ->and($output)->toContain('declares [/articles/{id}]')
        ->and($output)->toContain('but the route it annotates is [GET /articles/{article_id}]');
});

it('fails when the declared path is a typo away from the route', function (): void {
    Route::get('article/{id}', ShowArticleController::class);

    [$status, $output] = validateSchema();

    expect($status)->toBe(1)
        ->and($output)->toContain('declares [/articles/{id}]')
        ->and($output)->toContain('[GET /article/{id}]');
});

it('fails when the declared path is right but the method is not', function (): void {
    Route::post('articles/{id}', ShowArticleController::class);

    [$status, $output] = validateSchema();

    expect($status)->toBe(1)
        ->and($output)->toContain('declares [get /articles/{id}]')
        ->and($output)->toContain('but the route it annotates is [POST /articles/{id}]');
});

it('fails when the paths still carry the prefix the server base now adds', function (): void {
    $this->withConfig(['openapi.openapi.servers' => [['url' => '/articles']]]);

    Route::get('articles/{id}', ShowArticleController::class);

    [$status, $output] = validateSchema();

    expect($status)->toBe(1)
        ->and($output)->toContain('declares [/articles/{id}], which resolves to [/articles/articles/{id}] against the server base [/articles],')
        ->and($output)->toContain('but the route it annotates is [GET /articles/{id}]');
});

it('resolves declared paths against a configured server base', function (): void {
    $this->withConfig([
        'openapi.openapi.servers' => [['url' => 'https://api.example.com/v1']],
        'openapi.route.prefix' => 'v1',
    ]);

    Route::get('v1/articles/{id}', ShowArticleController::class);

    [$status, $output] = validateSchema();

    expect($status)->toBe(0)
        ->and($output)->toContain('valid OpenAPI 3.0.4 document (2 paths)');
});

it('treats an absolute server url with no path as a base of nothing', function (): void {
    $this->withConfig(['openapi.openapi.servers' => [['url' => 'https://api.example.com']]]);

    Route::get('articles/{id}', ShowArticleController::class);

    [$status, $output] = validateSchema();

    expect($status)->toBe(0)->and($output)->toContain('valid OpenAPI 3.0.4 document');
});

it('accepts any configured base, not only the first', function (): void {
    $this->withConfig([
        'openapi.openapi.servers' => [['url' => '/v1'], ['url' => '/']],
        'openapi.route.prefix' => 'v1',
    ]);

    Route::get('articles/{id}', ShowArticleController::class);

    [$status, $output] = validateSchema();

    expect($status)->toBe(0)->and($output)->toContain('valid OpenAPI 3.0.4 document');
});

it('names every base when a declaration matches none of them', function (): void {
    $this->withConfig(['openapi.openapi.servers' => [['url' => '/v1'], ['url' => '/v2']]]);

    Route::get('articles/{id}', ShowArticleController::class);

    [$status, $output] = validateSchema();

    expect($status)->toBe(1)
        ->and($output)->toContain('resolves to [/v1/articles/{id}, /v2/articles/{id}] against the server bases [/v1, /v2]');
});

it('accepts an optional parameter and a binding field, which a declaration never spells', function (string $uri): void {
    Route::get($uri, ShowArticleController::class);

    [$status, $output] = validateSchema();

    expect($status)->toBe(0)->and($output)->toContain('valid OpenAPI 3.0.4 document');
})->with(['articles/{id?}', 'articles/{id:slug}']);

it('accepts an attribute declaring several paths when one of them is its route', function (): void {
    Route::get('canonical/{id}', MultiPathController::class);

    [$status, $output] = validateSchema();

    expect($status)->toBe(0)
        ->and($output)->toContain('valid OpenAPI 3.0.4 document (3 paths)');
});

it('names both declared paths when neither is the route', function (): void {
    Route::get('neither/{id}', MultiPathController::class);

    [$status, $output] = validateSchema();

    expect($status)->toBe(1)
        ->and($output)->toContain('declares [/aliased/{id}, /canonical/{id}]')
        ->and($output)->toContain('[GET /neither/{id}]');
});

it('skips the path check rather than guessing at a server base with a variable', function (): void {
    $this->withConfig(['openapi.openapi.servers' => [[
        'url' => 'https://{tenant}.example.com/v1',
        'variables' => ['tenant' => ['default' => 'acme']],
    ]]]);

    Route::get('articles/{id}', ShowArticleController::class);

    [$status, $output] = validateSchema();

    expect($status)->toBe(0)
        ->and($output)->toContain('a server URL carries a {variable}')
        ->and($output)->not->toContain('The attribute on');
});

it('ignores a server entry that is not a server object rather than reading a base out of it', function (): void {
    $this->withConfig(['openapi.openapi.servers' => ['not-a-server']]);

    Route::get('articles/{id}', ShowArticleController::class);

    [$status, $output] = validateSchema();

    expect($status)->toBe(1)->and($output)->not->toContain('The attribute on');
});

it('ignores routes that declare nothing and closures that cannot', function (): void {
    Route::post('undocumented', UndocumentedController::class);
    Route::get('health', fn (): string => 'ok');

    [$status, $output] = validateSchema();

    expect($status)->toBe(0)->and($output)->toContain('valid OpenAPI 3.0.4 document');
});

it('reports a path mismatch alongside the security and specification errors', function (): void {
    Route::get('articles/{article_id}', ShowArticleController::class);
    Route::get('undeclared-security', UndeclaredSecuritySchemeController::class);
    Route::get('missing-responses', MissingResponsesController::class);

    [$status, $output] = validateSchema();

    expect($status)->toBe(1)
        ->and($output)->toContain('but the route it annotates is [GET /articles/{article_id}]')
        ->and($output)->toContain("Mentioned security scheme 'sanctum' not found in the given spec")
        ->and($output)->toContain('Operation is missing required property: responses');
});

it('leaves a deliberately decoupled document alone when the check is turned off', function (): void {
    $this->withConfig(['openapi.validation.declared_paths' => false]);

    Route::get('articles/{article_id}', ShowArticleController::class);

    [$status, $output] = validateSchema();

    expect($status)->toBe(0)->and($output)->toContain('valid OpenAPI 3.0.4 document');
});
