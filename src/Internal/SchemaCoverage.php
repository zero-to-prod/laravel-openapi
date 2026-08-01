<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi\Internal;

use JsonException;
use RuntimeException;

/**
 * @internal
 * Records which declared operations a test suite actually exercised.
 *
 * A response that matches its schema only proves the endpoints the suite
 * happens to reach are honest. Anything declared but never exercised is
 * unverified, so the state is deliberately static: it accumulates across
 * tests within a process.
 */
class SchemaCoverage
{
    /** The OpenAPI operations a Path Item may declare. */
    private const array operations = ['get', 'put', 'post', 'delete', 'options', 'head', 'patch', 'trace'];

    /**
     * Exercised responses, as [path][method][status].
     *
     * @var array<string, array<string, array<int, true>>>
     */
    private static array $exercised = [];

    /**
     * Only responses not already seen reach the disk, so a suite appends once
     * per distinct operation rather than once per assertion.
     *
     * @throws JsonException
     */
    public static function record(string $path, string $method, int $status): void
    {
        $method = strtolower($method);

        if (isset(self::$exercised[$path][$method][$status])) {
            return;
        }

        self::$exercised[$path][$method][$status] = true;

        self::persist($path, $method, $status);
    }

    /**
     * Merge coverage recorded by earlier processes into this one. Parallel test
     * workers each append, so the file is the union of every run since the last
     * purge.
     */
    public static function load(): void
    {
        if (!is_file($file = self::path())) {
            return;
        }

        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $record = json_decode($line, true);

            if (is_array($record) && isset($record['path'], $record['method'], $record['status'])) {
                self::$exercised[$record['path']][strtolower((string)$record['method'])][(int)$record['status']] = true;
            }
        }
    }

    /**
     * Every response the document declares.
     *
     * @param  array<string, mixed>  $document
     *
     * @return list<string>
     */
    public static function declared(array $document): array
    {
        $declared = array_map(
            static fn(array $response): string => self::describe(...$response),
            self::responses($document)
        );

        sort($declared);

        return $declared;
    }

    /**
     * Declared responses that no test exercised.
     *
     * @param  array<string, mixed>  $document
     *
     * @return list<string>
     */
    public static function missing(array $document): array
    {
        $missing = [];

        foreach (self::responses($document) as [$path, $method, $status]) {
            if (!self::covers($path, $method, $status)) {
                $missing[] = self::describe($path, $method, $status);
            }
        }

        sort($missing);

        return $missing;
    }

    /**
     * Every declared response, as [path, method, status].
     *
     * @param  array<string, mixed>  $document
     *
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private static function responses(array $document): array
    {
        $responses = [];

        foreach ($document['paths'] ?? [] as $path => $pathItem) {
            if (!is_array($pathItem)) {
                continue;
            }

            foreach (self::operations as $method) {
                if (!is_array($pathItem[$method] ?? null)) {
                    continue;
                }

                foreach (array_keys($pathItem[$method]['responses'] ?? []) as $status) {
                    $responses[] = [(string)$path, $method, (string)$status];
                }
            }
        }

        return $responses;
    }

    /**
     * @return list<string>
     */
    public static function exercised(): array
    {
        $exercised = [];

        foreach (self::$exercised as $path => $methods) {
            foreach ($methods as $method => $statuses) {
                foreach (array_keys($statuses) as $status) {
                    $exercised[] = self::describe($path, $method, (string)$status);
                }
            }
        }

        sort($exercised);

        return $exercised;
    }

    /**
     * Forget coverage recorded in this process, leaving the persisted file
     * alone. Mainly useful for proving that persistence works.
     */
    public static function flush(): void
    {
        self::$exercised = [];
    }

    /**
     * Discard coverage entirely, in memory and on disk. Run before a suite, not
     * between tests: parallel workers share the file.
     */
    public static function purge(): void
    {
        self::flush();

        if (is_file($file = self::path())) {
            unlink($file);
        }
    }

    public static function path(): string
    {
        return (string)config(
            'openapi.coverage.path',
            storage_path('framework/cache/openapi-coverage.jsonl')
        );
    }

    /**
     * Appended as JSON Lines so concurrent workers can each add records
     * without coordinating.
     *
     * @throws JsonException
     */
    private static function persist(string $path, string $method, int $status): void
    {
        $file = self::path();
        $directory = dirname($file);

        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create the coverage directory [%s].', $directory));
        }

        $line = json_encode(
                ['path' => $path, 'method' => $method, 'status' => $status],
                JSON_THROW_ON_ERROR
            ).PHP_EOL;

        if (file_put_contents($file, $line, FILE_APPEND | LOCK_EX) === false) {
            throw new RuntimeException(sprintf('Unable to write coverage to [%s].', $file));
        }
    }

    /**
     * A declared response may be a concrete status, a `2XX` range, or `default`.
     */
    private static function covers(string $path, string $method, string $declared): bool
    {
        foreach (array_keys(self::$exercised[$path][$method] ?? []) as $status) {
            if ($declared === 'default' || $declared === (string)$status) {
                return true;
            }

            if (preg_match('/^([1-5])XX$/i', $declared, $matches) === 1
                && intdiv($status, 100) === (int)$matches[1]
            ) {
                return true;
            }
        }

        return false;
    }

    private static function describe(string $path, string $method, string $status): string
    {
        return sprintf('%s %s -> %s', strtoupper($method), $path, $status);
    }
}
