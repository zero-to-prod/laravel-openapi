<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\AssertionFailedError;
use ZeroToProd\LaravelOpenapi\Internal\Mcp\Server;
use ZeroToProd\LaravelOpenapi\Internal\Mcp\Tools\Example;
use ZeroToProd\LaravelOpenapi\Internal\SchemaCoverage;
use ZeroToProd\LaravelOpenapi\Tests\Fixtures\SecureArticleController;
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
    Route::get('secure-articles', SecureArticleController::class);
});

afterEach(fn () => SchemaCoverage::purge());

it('describes the tool so an agent knows when to call it', function (): void {
    $tool = new Example;

    expect($tool->name())->toBe('example')
        ->and($tool->description())->toContain('endpoint')
        ->and($tool->description())->toContain('rules')
        ->and($tool->toArray()['inputSchema']['properties'])->toHaveKey('topic');
});

it('returns the rules and the failure table by default, not the whole example', function (): void {
    Server::tool(Example::class)
        ->assertOk()
        ->assertHasNoErrors()
        ->assertName('example')
        ->assertSee([
            '## Rules that decide whether this works',
            '## What a failure is telling you',
            'One attribute per controller method',
        ])
        ->assertDontSee([
            '# Implementing and testing an endpoint',
            '## 1. Set up the application once',
            'class ShowArticleController',
        ]);
});

it('names the other topics so an agent knows the rest exists', function (): void {
    Server::tool(Example::class)->assertSee([
        'setup, attribute, routing, testing, coverage, requestBody, security, failures, all',
        '{"topic": "all"}',
    ]);
});

it('returns the complete worked example on request', function (): void {
    Server::tool(Example::class, ['topic' => 'all'])
        ->assertOk()
        ->assertSee([
            '# Implementing and testing an endpoint',
            '## 1. Set up the application once',
            '## 7. Endpoints behind authentication',
            '## What a failure is telling you',
        ]);
});

it('returns only the named section for a single topic', function (string $topic, string $heading): void {
    Server::tool(Example::class, ['topic' => $topic])
        ->assertOk()
        ->assertSee($heading)
        ->assertDontSee('## Rules that decide whether this works');
})->with([
    ['setup', '## 1. Set up the application once'],
    ['attribute', '## 2. Declare the endpoint on the controller method'],
    ['routing', '## 3. Register the route'],
    ['testing', '## 4. Test every response you declared'],
    ['coverage', '## 5. Gate it in CI'],
    ['requestBody', '## 6. Endpoints that accept a body'],
    ['security', '## 7. Endpoints behind authentication'],
]);

it('returns just the failure table for the failures topic', function (): void {
    Server::tool(Example::class, ['topic' => 'failures'])
        ->assertSee('## What a failure is telling you')
        ->assertDontSee('## Rules that decide whether this works');
});

it('still makes progress when the topic is a guess that missed', function (): void {
    Server::tool(Example::class, ['topic' => 'authentication'])
        ->assertOk()
        ->assertHasNoErrors()
        ->assertSee([
            'There is no `authentication` topic.',
            'setup, attribute, routing, testing, coverage, requestBody, security, failures, all',
            '## Rules that decide whether this works',
        ]);
});

it('treats an empty topic as no topic at all', function (): void {
    Server::tool(Example::class, ['topic' => ''])
        ->assertSee('## Rules that decide whether this works')
        ->assertDontSee('There is no');
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
        ->toContain(snippet('StoreArticleController'))
        ->toContain(snippet('SecureArticleController'));
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

it('serves a secured 200 to a request carrying the declared credential', function (): void {
    $this->assertMatchesSchema($this->withToken('valid-token')->getJson('secure-articles'))
        ->assertOk()
        ->assertJsonPath('articles.0', 'Zero to prod');
});

it('reaches the declared 401 when the header is present but the guard rejects it', function (): void {
    $this->assertMatchesSchema($this->withToken('wrong-token')->getJson('secure-articles'))
        ->assertUnauthorized();
});

it('fails on the request, not the response, when the credential is missing entirely', function (): void {
    // The trap the security topic exists to warn about: with no header there is
    // no way to reach the 401, so it can never be covered.
    expect(fn () => $this->assertMatchesSchema($this->getJson('secure-articles')))
        ->toThrow(AssertionFailedError::class, 'None of security schemas did match');
});

it('records each example response the way the coverage gate expects', function (): void {
    $this->assertMatchesSchema($this->getJson('articles/42'));
    $this->assertMatchesSchema($this->getJson('articles/99'));
    $this->assertMatchesSchema($this->postJson('articles', ['title' => 'Zero to prod']));
    $this->assertMatchesSchema($this->postJson('articles', ['title' => '  ']));
    $this->assertMatchesSchema($this->withToken('valid-token')->getJson('secure-articles'));
    $this->assertMatchesSchema($this->withToken('wrong-token')->getJson('secure-articles'));

    expect(SchemaCoverage::exercised())->toBe([
        'GET /articles/{id} -> 200',
        'GET /articles/{id} -> 404',
        'GET /secure-articles -> 200',
        'GET /secure-articles -> 401',
        'POST /articles -> 201',
        'POST /articles -> 422',
    ]);
});
