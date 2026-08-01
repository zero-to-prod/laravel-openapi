<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use ZeroToProd\LaravelOpenapi\SchemaGenerator;
use ZeroToProd\LaravelOpenapi\Tests\Fixtures\ZeroValuedController;

it('serves the document-level fields from config', function (): void {
    $this->getJson('openapi.json')
        ->assertOk()
        ->assertJsonPath('openapi', '3.0.4')
        ->assertJsonPath('info.title', 'JSON:API')
        ->assertJsonPath('servers.0.url', '/');
});

it('documents routes declaring a #[ApiSchema] attribute', function (): void {
    // Declared paths contain dots, so dot-notation lookups would split them.
    $operation = $this->getJson('openapi.json')->assertOk()->json('paths')['/openapi.json']['get'];

    expect($operation['operationId'])->toBe('getSchema')
        ->and($operation['responses']['200']['content']['application/json']['schema']['properties']['openapi']['type'])
        ->toBe('string');
});

it('omits routes without a #[ApiSchema] attribute', function (): void {
    Route::get('/undocumented', static fn (): null => null);

    expect(array_keys(app(SchemaGenerator::class)->document()['paths']))->toBe(['/openapi.json']);
});

it('serves meaningful falsy values instead of dropping them', function (): void {
    Route::get('zero-valued', ZeroValuedController::class);

    $schema = $this->getJson('openapi.json')
        ->json('paths')['/zero-valued']['get']['responses']['200']['content']['application/vnd.api+json']['schema'];

    expect($schema)->toBe([
        'type' => 'integer',
        'minimum' => 0,
        'example' => 0,
        'default' => 0,
    ]);
});
