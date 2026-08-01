<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\AssertionFailedError;
use ZeroToProd\LaravelOpenapi\SchemaGenerator;
use ZeroToProd\LaravelOpenapi\Testing\SchemaCoverage;
use ZeroToProd\LaravelOpenapi\Tests\Fixtures\LyingController;
use ZeroToProd\LaravelOpenapi\Tests\Fixtures\UndeclaredStatusController;

beforeEach(function (): void {
    config(['openapi.coverage.path' => sys_get_temp_dir().'/openapi-coverage-test/behavior.jsonl']);
    SchemaCoverage::purge();
});

afterEach(fn () => SchemaCoverage::purge());

it('matches the schema endpoint against its own declaration', function (): void {
    $this->assertMatchesSchema($this->getJson('openapi.json')->assertOk());
});

it('fails when a response contradicts its declared schema', function (): void {
    Route::get('lying', LyingController::class);

    $failure = null;

    try {
        $this->assertMatchesSchema($this->getJson('lying')->assertOk());
    } catch (AssertionFailedError $e) {
        $failure = $e->getMessage();
    }

    expect($failure)->not->toBeNull('A response contradicting the schema was accepted.')
        ->and($failure)->toContain('Body does not match schema')
        ->and($failure)->toContain('caused by:');
});

it('fails when a route responds with an undeclared status code', function (): void {
    Route::get('undeclared-status', UndeclaredStatusController::class);

    $failure = null;

    try {
        $this->assertMatchesSchema($this->getJson('undeclared-status'));
    } catch (AssertionFailedError $e) {
        $failure = $e->getMessage();
    }

    expect($failure)->not->toBeNull('An undeclared status code was accepted.')
        ->and($failure)->toContain('418');
});

it('records an operation as exercised once its response is validated', function (): void {
    expect(SchemaCoverage::exercised())->toBe([]);

    $this->assertMatchesSchema($this->getJson('openapi.json'));

    expect(SchemaCoverage::exercised())->toBe(['GET /openapi.json -> 200'])
        ->and(SchemaCoverage::missing(app(SchemaGenerator::class)->document()))->toBe([]);
});

it('reports declared responses that no test exercised', function (): void {
    Route::get('lying', LyingController::class);

    expect(SchemaCoverage::missing(app(SchemaGenerator::class)->document()))
        ->toBe(['GET /lying -> 200', 'GET /openapi.json -> 200']);
});

it('treats a range or default declaration as covered by a concrete status', function (): void {
    SchemaCoverage::record('/x', 'get', 201);

    $document = ['paths' => ['/x' => ['get' => ['responses' => [
        '2XX' => [], 'default' => [], '201' => [], '404' => [],
    ]]]]];

    expect(SchemaCoverage::missing($document))->toBe(['GET /x -> 404']);
});
