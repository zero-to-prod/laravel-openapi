<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\AssertionFailedError;
use ZeroToProd\LaravelOpenapi\Internal\Mcp\Server;
use ZeroToProd\LaravelOpenapi\Internal\Mcp\Tools\Example;
use ZeroToProd\LaravelOpenapi\Internal\SchemaCoverage;
use ZeroToProd\LaravelOpenapi\Tests\Fixtures\ApiRoute;
use ZeroToProd\LaravelOpenapi\Tests\Fixtures\EnumConstructedController;
use ZeroToProd\LaravelOpenapi\Tests\Fixtures\EnumConstructedSchema;
use ZeroToProd\LaravelOpenapi\Tests\Fixtures\SecureArticleController;
use ZeroToProd\LaravelOpenapi\Tests\Fixtures\ShowArticleController;
use ZeroToProd\LaravelOpenapi\Tests\Fixtures\StoreArticleController;
use ZeroToProd\LaravelOpenapi\Tests\Fixtures\SubApiSchema;
use ZeroToProd\LaravelOpenapi\Tests\Fixtures\SubclassSchemaController;

function snippet(string $class): string
{
    $source = (string) file_get_contents(dirname(__DIR__).'/Fixtures/'.$class.'.php');

    return trim(substr($source, (int) strpos($source, 'use Illuminate')));
}

function text(array $arguments = []): string
{
    return mcpText(Server::tool(Example::class, $arguments));
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
        ->and($tool->description())->toContain('start')
        ->and($tool->toArray()['inputSchema']['properties'])->toHaveKey('topic');
});

it('leads with the attribute shape by default, not the failure table', function (): void {
    Server::tool(Example::class)
        ->assertOk()
        ->assertHasNoErrors()
        ->assertName('example')
        ->assertSee([
            '# Documenting an endpoint',
            '#[ApiSchema([',
            "'operationId' => 'showArticle',",
            'assertMatchesSchema',
        ])
        ->assertDontSee([
            '# Implementing and testing an endpoint',
            '## 1. Set up the application once',
            '## What a failure is telling you',
        ]);
});

it('carries the four rules that otherwise cost a test cycle', function (): void {
    Server::tool(Example::class)->assertSee([
        'The response `Content-Type` has to match the declared media type.',
        'Declare every status the method can return.',
        'The request is validated before the response.',
        "->withToken('any-value')",
    ]);
});

it('stays small enough to be the payload an agent always pays for', function (): void {
    expect(strlen(text()))->toBeLessThan(2600);
});

it('names the other topics so an agent knows the rest exists', function (): void {
    Server::tool(Example::class)->assertSee([
        'start, setup, attribute, routing, testing, coverage, requestBody, security, rules, failures, all',
        '`all` for the complete worked example',
    ]);
});

it('returns the rules on request, without the failure table it used to drag along', function (): void {
    Server::tool(Example::class, ['topic' => 'rules'])
        ->assertOk()
        ->assertSee([
            '## Rules that decide whether this works',
            'One attribute per controller method',
        ])
        ->assertDontSee(['## What a failure is telling you', 'Topics:']);
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
            'start, setup, attribute, routing, testing, coverage, requestBody, security, rules, failures, all',
            '# Documenting an endpoint',
        ]);
});

it('treats an empty topic as no topic at all', function (): void {
    Server::tool(Example::class, ['topic' => ''])
        ->assertSee('# Documenting an endpoint')
        ->assertDontSee('There is no');
});

it('says nothing about a local convention when the project uses the package attribute', function (): void {
    Server::tool(Example::class)->assertDontSee('## Local convention — read this first');
});

it('names the project-local subclass ahead of the generic shape', function (): void {
    Route::get('enum-constructed', EnumConstructedController::class);

    $text = text();

    expect($text)
        ->toContain('## Local convention — read this first')
        ->toContain('on 1 of 5 documented routes:')
        ->toContain(EnumConstructedSchema::class)
        ->toContain('__construct('.ApiRoute::class.' $ApiRoute)')
        ->and(strpos($text, '## Local convention'))->toBeLessThan((int) strpos($text, '# Documenting an endpoint'));
});

it('refuses to pretend the generic fragment fits a constructor that would not take it', function (): void {
    Route::get('enum-constructed', EnumConstructedController::class);

    Server::tool(Example::class)->assertSee([
        'It takes no OpenAPI fragment, so the shape below does not apply verbatim.',
        EnumConstructedController::class.'::__invoke',
    ]);
});

it('says the generic example applies directly when the subclass takes a fragment', function (): void {
    Route::get('sub', SubclassSchemaController::class);

    Server::tool(Example::class)
        ->assertSee([
            SubApiSchema::class,
            '__construct(array $schema)',
            'It takes an OpenAPI fragment, so the shape below applies as written',
        ])
        ->assertDontSee('does not apply verbatim');
});

it('prepends the convention to the topics that show the attribute, and to nothing else', function (string $topic, bool $expected): void {
    Route::get('enum-constructed', EnumConstructedController::class);

    expect(str_contains(text(['topic' => $topic]), '## Local convention'))->toBe($expected);
})->with([
    ['attribute', true],
    ['all', true],
    ['start', true],
    ['rules', false],
    ['failures', false],
    ['security', false],
]);

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
