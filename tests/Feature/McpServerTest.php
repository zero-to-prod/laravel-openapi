<?php

declare(strict_types=1);

use Laravel\Mcp\Server\Registrar;
use ZeroToProd\LaravelOpenapi\Mcp\OpenapiServer;
use ZeroToProd\LaravelOpenapi\Mcp\Tools\Readme;

it('registers the server under the laravel-openapi handle', function (): void {
    expect($this->app->make(Registrar::class)->getLocalServer('laravel-openapi'))->not->toBeNull();
});

it('registers nothing when the server is disabled', function (): void {
    $this->withConfig(['openapi.mcp.enabled' => false]);

    expect($this->app->make(Registrar::class)->getLocalServer('laravel-openapi'))->toBeNull();
});

it('registers under a configured handle', function (): void {
    $this->withConfig(['openapi.mcp.handle' => 'openapi-docs']);

    $registrar = $this->app->make(Registrar::class);

    expect($registrar->getLocalServer('openapi-docs'))->not->toBeNull()
        ->and($registrar->getLocalServer('laravel-openapi'))->toBeNull();
});

it('returns the readme', function (): void {
    OpenapiServer::tool(Readme::class)
        ->assertOk()
        ->assertHasNoErrors()
        ->assertName('readme')
        ->assertSee(file_get_contents(Readme::path()));
});

it('describes the tool so an agent knows when to call it', function (): void {
    $tool = new Readme;

    expect($tool->name())->toBe('readme')
        ->and($tool->description())->toContain('#[ApiSchema]');
});
