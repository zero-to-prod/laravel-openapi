<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi\Internal;

use Illuminate\Support\Facades\Config;
use JsonException;
use RuntimeException;

/** @internal */
class SchemaCoverage
{
    private const array operations = ['get', 'put', 'post', 'delete', 'options', 'head', 'patch', 'trace'];

    /** @var array<string, array<string, array<int, true>>> */
    private static array $exercised = [];

    /** @throws JsonException */
    public static function record(string $path, string $method, int $status): void
    {
        $method = strtolower($method);

        if (isset(self::$exercised[$path][$method][$status])) {
            return;
        }

        self::$exercised[$path][$method][$status] = true;

        self::persist($path, $method, $status);
    }

    public static function load(): void
    {
        if (! is_file($file = self::path())) {
            return;
        }

        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $record = json_decode($line, true);

            if (is_array($record) && isset($record['path'], $record['method'], $record['status'])) {
                self::$exercised[$record['path']][strtolower((string) $record['method'])][(int) $record['status']] = true;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $document
     * @return list<string>
     */
    public static function declared(array $document): array
    {
        $declared = array_map(
            static fn (array $response): string => self::describe(...$response),
            self::responses($document)
        );

        sort($declared);

        return $declared;
    }

    /**
     * @param  array<string, mixed>  $document
     * @return list<string>
     */
    public static function missing(array $document): array
    {
        $missing = [];

        foreach (self::responses($document) as [$path, $method, $status]) {
            if (! self::covers($path, $method, $status)) {
                $missing[] = self::describe($path, $method, $status);
            }
        }

        sort($missing);

        return $missing;
    }

    /**
     * @param  array<string, mixed>  $document
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private static function responses(array $document): array
    {
        $responses = [];
        $paths = $document['paths'] ?? [];

        if (! is_array($paths)) {
            return [];
        }

        foreach ($paths as $path => $pathItem) {
            if (! is_array($pathItem)) {
                continue;
            }

            foreach (self::operations as $method) {
                $operation = $pathItem[$method] ?? null;

                if (! is_array($operation)) {
                    continue;
                }

                $declared = $operation['responses'] ?? [];

                if (! is_array($declared)) {
                    continue;
                }

                foreach (array_keys($declared) as $status) {
                    $responses[] = [(string) $path, $method, (string) $status];
                }
            }
        }

        return $responses;
    }

    /** @return list<string> */
    public static function exercised(): array
    {
        $exercised = [];

        foreach (self::$exercised as $path => $methods) {
            foreach ($methods as $method => $statuses) {
                foreach (array_keys($statuses) as $status) {
                    $exercised[] = self::describe($path, $method, (string) $status);
                }
            }
        }

        sort($exercised);

        return $exercised;
    }

    public static function flush(): void
    {
        self::$exercised = [];
    }

    public static function purge(): void
    {
        self::flush();

        if (is_file($file = self::path())) {
            unlink($file);
        }
    }

    public static function path(): string
    {
        return Config::string(
            'openapi.coverage.path',
            storage_path('framework/cache/openapi-coverage.jsonl')
        );
    }

    /** @throws JsonException */
    private static function persist(string $path, string $method, int $status): void
    {
        $file = self::path();
        $directory = dirname($file);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
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

    private static function covers(string $path, string $method, string $declared): bool
    {
        foreach (array_keys(self::$exercised[$path][$method] ?? []) as $status) {
            if ($declared === 'default' || $declared === (string) $status) {
                return true;
            }

            if (preg_match('/^([1-5])XX$/i', $declared, $matches) === 1
                && intdiv($status, 100) === (int) $matches[1]
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
