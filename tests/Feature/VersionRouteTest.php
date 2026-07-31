<?php

declare(strict_types=1);

it('exposes the jsonapi version route', function (): void {
    $this->getJson('jsonapi/version')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertExactJson([
            'jsonapi' => ['version' => '1.1'],
        ]);
});

it('registers the route under the configured prefix', function (): void {
    expect(route('jsonapi.version', absolute: false))
        ->toBe('/'.config('jsonapi.route.prefix').'/version');
});
