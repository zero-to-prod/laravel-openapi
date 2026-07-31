<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Zerotoprod\DataModelOpenapi30\OpenApi;
use ZeroToProd\LaravelOpenapi\SchemaGenerator;
use ZeroToProd\LaravelOpenapi\Tests\Fixtures\ZeroValuedController;

it('serves the document-level fields from config', function (): void {
    $this->getJson('openapi/schema')
        ->assertOk()
        ->assertJsonPath('openapi', '3.0.4')
        ->assertJsonPath('info.title', 'JSON:API')
        ->assertJsonPath('servers.0.url', '/openapi');
});

it('documents routes declaring a #[ApiSchema] attribute', function (): void {
    $response = $this->getJson('openapi/schema')
        ->assertJsonPath('paths./schema.get.operationId', 'getSchema');

    $content = $response->json('paths./schema.get.responses.200.content');

    expect($content['application/json']['schema']['properties']['openapi']['type'])->toBe('string');
});

it('omits routes without a #[ApiSchema] attribute', function (): void {
    Route::get('/undocumented', static fn () => null);

    expect(array_keys(app(SchemaGenerator::class)->document()[OpenApi::paths]))->toBe(['/schema']);
});

it('serves meaningful falsy values instead of dropping them', function (): void {
    Route::get('openapi/zero-valued', ZeroValuedController::class);

    $schema = $this->getJson('openapi/schema')
        ->json('paths./zero-valued.get.responses.200.content')['application/vnd.api+json']['schema'];

    expect($schema)->toBe([
        'type' => 'integer',
        'minimum' => 0,
        'example' => 0,
        'default' => 0,
    ]);
});
