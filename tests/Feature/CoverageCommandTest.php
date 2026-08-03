<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use ZeroToProd\LaravelOpenapi\Internal\SchemaCoverage;
use ZeroToProd\LaravelOpenapi\SchemaGenerator;
use ZeroToProd\LaravelOpenapi\Tests\Fixtures\LyingController;

beforeEach(function (): void {
    config(['openapi.coverage.path' => sys_get_temp_dir().'/openapi-coverage-test/coverage.jsonl']);
    SchemaCoverage::purge();
});

afterEach(fn () => SchemaCoverage::purge());

function coverage(array $options = []): array
{
    return [Artisan::call('openapi:coverage', $options), Artisan::output()];
}

it('fails when nothing was recorded', function (): void {
    [$status, $output] = coverage();

    expect($status)->toBe(1)
        ->and($output)->toContain('1 of 1 declared responses were never exercised')
        ->and($output)->toContain('GET /openapi.json -> 200')
        ->and($output)->toContain('No coverage was recorded at all');
});

it('reads coverage recorded before the current process', function (): void {
    $this->assertMatchesSchema($this->getJson('openapi.json'));

    // Drop the in-memory record, leaving only the persisted file, as a separate
    // process would see it.
    SchemaCoverage::flush();

    expect(SchemaCoverage::missing(app(SchemaGenerator::class)->document()))
        ->toBe(['GET /openapi.json -> 200']);

    [$status, $output] = coverage();

    expect($status)->toBe(0)
        ->and($output)->toContain('Every declared response was exercised (1 of 1)');
});

it('still fails when only some declared responses were exercised', function (): void {
    Route::get('lying', LyingController::class);

    SchemaCoverage::record('/openapi.json', 'get', 200);
    SchemaCoverage::flush();

    [$status, $output] = coverage();

    expect($status)->toBe(1)
        ->and($output)->toContain('1 of 2 declared responses were never exercised')
        ->and($output)->toContain('GET /lying -> 200')
        ->and($output)->not->toContain('No coverage was recorded at all');
});

it('appends one record per distinct response, not per assertion', function (): void {
    $this->assertMatchesSchema($this->getJson('openapi.json'));
    $this->assertMatchesSchema($this->getJson('openapi.json'));
    $this->assertMatchesSchema($this->getJson('openapi.json'));

    expect(file(SchemaCoverage::path(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))
        ->toHaveCount(1);
});

it('warns when the document declares no responses at all', function (): void {
    // With the package route disabled nothing declares a response, so there is
    // no denominator to report against.
    $this->withConfig([
        'openapi.route.enabled' => false,
        'openapi.coverage.path' => sys_get_temp_dir().'/openapi-coverage-test/coverage.jsonl',
    ]);

    [$status, $output] = coverage();

    expect($status)->toBe(0)
        ->and($output)->toContain('declares no responses');
});

it('discards recorded coverage on --reset', function (): void {
    SchemaCoverage::record('/openapi.json', 'get', 200);

    expect(SchemaCoverage::path())->toBeFile();

    [$status, $output] = coverage(['--reset' => true]);

    expect($status)->toBe(0)
        ->and($output)->toContain('Discarded recorded coverage')
        ->and(SchemaCoverage::path())->not->toBeFile();
});
