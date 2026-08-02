<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use ZeroToProd\LaravelOpenapi\Internal\Mcp\Server;
use ZeroToProd\LaravelOpenapi\Internal\Mcp\Tools\Example;
use ZeroToProd\LaravelOpenapi\Internal\SchemaCoverage;
use ZeroToProd\LaravelOpenapi\Tests\Fixtures\ShowArticleController;
use ZeroToProd\LaravelOpenapi\Tests\Fixtures\StoreArticleController;

/**
 * The controllers the tool holds as text are kept in tests/Fixtures so the
 * suite can register them and prove they validate. This returns a fixture from
 * its first `use` statement onward, which is the snippet the tool embeds.
 */
function snippet(string $class): string
{
    $source = (string) file_get_contents(dirname(__DIR__).'/Fixtures/'.$class.'.php');

    return trim(substr($source, (int) strpos($source, 'use Illuminate')));
}

beforeEach(function (): void {
    config(['openapi.coverage.path' => sys_get_temp_dir().'/openapi-coverage-test/example.jsonl']);
    SchemaCoverage::purge();

    Route::get('articles/{id}', ShowArticleController::class);
    Route::post('articles', StoreArticleController::class);
});

afterEach(fn () => SchemaCoverage::purge());

it('describes the tool so an agent knows when to call it', function (): void {
    $tool = new Example;

    expect($tool->name())->toBe('example')
        ->and($tool->description())->toContain('endpoint');
});

it('returns the example', function (): void {
    Server::tool(Example::class)
        ->assertOk()
        ->assertHasNoErrors()
        ->assertName('example')
        ->assertSee('# Implementing and testing an endpoint');
});

it('walks an agent through every step of the workflow', function (): void {
    expect(Example::content())
        ->toContain('use ZeroToProd\LaravelOpenapi\ValidatesSchema;')
        ->toContain('#[ApiSchema([')
        ->toContain("Route::get('articles/{id}', ShowArticleController::class);")
        ->toContain('assertMatchesSchema')
        ->toContain('php artisan openapi:validate')
        ->toContain('php artisan openapi:coverage --reset && vendor/bin/pest && php artisan openapi:coverage');
});

it('embeds the controllers verbatim from the fixtures the suite exercises', function (): void {
    expect(Example::content())
        ->toContain(snippet('ShowArticleController'))
        ->toContain(snippet('StoreArticleController'));
});

it('serves a 200 that matches the example declaration', function (): void {
    $this->assertMatchesSchema($this->getJson('articles/42'))
        ->assertOk()
        ->assertJsonPath('title', 'Zero to prod');
});

it('serves a 404 that matches the example declaration', function (): void {
    $this->assertMatchesSchema($this->getJson('articles/99')->assertNotFound());
});

it('serves a 201 that matches the example declaration', function (): void {
    $this->assertMatchesSchema($this->postJson('articles', ['title' => 'Zero to prod']))
        ->assertCreated();
});

it('serves a 422 that matches the example declaration', function (): void {
    $this->assertMatchesSchema($this->postJson('articles', ['title' => '  ']))
        ->assertUnprocessable();
});

it('records each example response the way the coverage gate expects', function (): void {
    $this->assertMatchesSchema($this->getJson('articles/42'));
    $this->assertMatchesSchema($this->getJson('articles/99'));
    $this->assertMatchesSchema($this->postJson('articles', ['title' => 'Zero to prod']));
    $this->assertMatchesSchema($this->postJson('articles', ['title' => '  ']));

    expect(SchemaCoverage::exercised())->toBe([
        'GET /articles/{id} -> 200',
        'GET /articles/{id} -> 404',
        'POST /articles -> 201',
        'POST /articles -> 422',
    ]);
});
