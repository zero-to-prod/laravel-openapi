<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Zerotoprod\DataModelOpenapi30\OpenApi;
use ZeroToProd\JsonApi\SchemaGenerator;

it('serves the document-level fields from config', function (): void {
    $this->getJson('jsonapi/schema')
        ->assertOk()
        ->assertJsonPath('openapi', '3.0.4')
        ->assertJsonPath('info.title', 'JSON:API')
        ->assertJsonPath('servers.0.url', '/jsonapi');
});

it('documents routes declaring a #[JsonApi] attribute', function (): void {
    $response = $this->getJson('jsonapi/schema')
        ->assertJsonPath('paths./schema.get.operationId', 'getSchema');

    $content = $response->json('paths./schema.get.responses.200.content');

    expect($content['application/json']['schema']['properties']['openapi']['type'])->toBe('string');
});

it('omits routes without a #[JsonApi] attribute', function (): void {
    Route::get('/undocumented', static fn () => null);

    expect(array_keys(app(SchemaGenerator::class)->generate()->paths))->toBe(['/schema']);
});

it('hydrates into an OpenApi data model', function (): void {
    $schema = app(SchemaGenerator::class)->generate();

    expect($schema)->toBeInstanceOf(OpenApi::class)
        ->and($schema->paths['/schema']->get->operationId)->toBe('getSchema')
        ->and($schema->info->title)->toBe('JSON:API');
});
