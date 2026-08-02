<?php

declare(strict_types=1);

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use ZeroToProd\LaravelOpenapi\SchemaGenerator;
use ZeroToProd\LaravelOpenapi\Tests\Fixtures\SubclassSchemaController;
use ZeroToProd\LaravelOpenapi\Tests\Fixtures\ZeroValuedController;

it('serves the document-level fields from config', function (): void {
    $this->getJson('openapi.json')
        ->assertOk()
        ->assertJsonPath('openapi', '3.0.4')
        ->assertJsonPath('info.title', 'Laravel')
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

it('omits a route whose action method does not exist on the controller', function (): void {
    // Registration does not verify the method, so reflection has to be guarded
    // rather than assumed to succeed.
    Route::get('/ghost', [ZeroValuedController::class, 'noSuchMethod']);

    expect(array_keys(app(SchemaGenerator::class)->document()['paths']))->toBe(['/openapi.json']);
});

it('picks up #[ApiSchema] subclass attributes with the default IS_INSTANCEOF flag', function (): void {
    Route::get('sub', SubclassSchemaController::class);

    expect(array_keys(app(SchemaGenerator::class)->document()['paths']))->toContain('/sub');
});

it('omits #[ApiSchema] subclass attributes when attributeFlags is 0', function (): void {
    Route::get('sub', SubclassSchemaController::class);

    $generator = new SchemaGenerator(app(Router::class), attributeFlags: 0);

    expect(array_keys($generator->document()['paths']))->not->toContain('/sub');
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
