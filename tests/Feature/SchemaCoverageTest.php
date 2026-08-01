<?php

declare(strict_types=1);

use ZeroToProd\LaravelOpenapi\Internal\SchemaCoverage;

afterEach(fn () => SchemaCoverage::purge());

/**
 * mkdir() and file_put_contents() both emit an E_WARNING before returning
 * false. PHPUnit promotes that warning to an exception, which would pre-empt
 * the RuntimeException these tests are about, so silence it for the call.
 */
function withoutWarnings(Closure $callback): Closure
{
    return function () use ($callback): void {
        set_error_handler(static fn (): bool => true);

        try {
            $callback();
        } finally {
            restore_error_handler();
        }
    };
}

it('ignores a document whose paths are not a map', function (): void {
    expect(SchemaCoverage::declared(['paths' => 'not-a-map']))->toBe([])
        ->and(SchemaCoverage::missing(['paths' => 'not-a-map']))->toBe([]);
});

it('skips a path item that is not a map', function (): void {
    $document = ['paths' => ['/x' => 'not-a-map']];

    expect(SchemaCoverage::declared($document))->toBe([])
        ->and(SchemaCoverage::missing($document))->toBe([]);
});

it('skips an operation whose responses are not a map', function (): void {
    $document = ['paths' => ['/x' => ['get' => ['responses' => 'not-a-map']]]];

    expect(SchemaCoverage::declared($document))->toBe([])
        ->and(SchemaCoverage::missing($document))->toBe([]);
});

it('ignores an operation that is not a map', function (): void {
    $document = ['paths' => ['/x' => ['get' => 'not-a-map']]];

    expect(SchemaCoverage::declared($document))->toBe([]);
});

it('fails when the coverage directory cannot be created', function (): void {
    // A regular file where the parent directory would go, so mkdir cannot
    // succeed and cannot be papered over by a concurrent writer.
    $blocker = (string) tempnam(sys_get_temp_dir(), 'openapi-blocker');

    config(['openapi.coverage.path' => $blocker.'/nested/coverage.jsonl']);

    try {
        expect(withoutWarnings(fn () => SchemaCoverage::record('/x', 'get', 200)))
            ->toThrow(RuntimeException::class, 'Unable to create the coverage directory');
    } finally {
        unlink($blocker);
    }
});

it('fails when the coverage file cannot be written', function (): void {
    // Point the path at a directory: the parent resolves, so persist() gets
    // past mkdir and fails on the write instead.
    $directory = sys_get_temp_dir().'/openapi-coverage-unwritable';

    is_dir($directory) || mkdir($directory, 0755, true);

    config(['openapi.coverage.path' => $directory]);

    try {
        expect(withoutWarnings(fn () => SchemaCoverage::record('/x', 'get', 200)))
            ->toThrow(RuntimeException::class, 'Unable to write coverage to');
    } finally {
        rmdir($directory);
    }
});
