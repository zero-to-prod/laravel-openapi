<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi\Internal;

use Symfony\Component\Process\Process;
use Throwable;

/**
 * @internal
 *
 * @phpstan-type Entry array{uri: string, methods: list<string>, action: string|null, middleware: list<string>, documented: bool, attribute: string|null, schema: array<string, mixed>}
 */
final class Inventory
{
    private const int TIMEOUT = 30;

    /** @return list<Entry>|null Null when the subprocess could not be trusted. */
    public static function entries(): ?array
    {
        $decoded = self::decode(self::output(['openapi:inventory', '--json']));

        return is_array($decoded) ? self::validate($decoded) : null;
    }

    /** @return array<string, mixed>|null Null when the subprocess could not be trusted. */
    public static function document(): ?array
    {
        $decoded = self::decode(self::output(['openapi:inventory', '--document']));

        if (! is_array($decoded) || array_is_list($decoded)) {
            return null;
        }

        $document = [];

        foreach ($decoded as $key => $value) {
            $document[(string) $key] = $value;
        }

        return $document;
    }

    /** @param  list<string>  $arguments */
    private static function output(array $arguments): ?string
    {
        $artisan = base_path('artisan');

        if (! is_file($artisan)) {
            return null;
        }

        $Process = new Process([PHP_BINARY, $artisan, ...$arguments], base_path());
        $Process->setTimeout(self::TIMEOUT);

        // @codeCoverageIgnoreStart
        try {
            $Process->run();
        } catch (Throwable) {
            return null;
        }
        // @codeCoverageIgnoreEnd

        return $Process->isSuccessful() ? $Process->getOutput() : null;
    }

    /**
     * @return array<mixed>|null
     */
    private static function decode(?string $output): ?array
    {
        foreach (array_reverse(preg_split('/\R/', trim((string) $output)) ?: []) as $line) {
            $decoded = json_decode(trim($line), true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * @param  array<mixed>  $decoded
     * @return list<Entry>|null
     */
    private static function validate(array $decoded): ?array
    {
        $entries = [];

        foreach ($decoded as $entry) {
            if (! is_array($entry)
                || ! is_string($entry['uri'] ?? null)
                || ! is_array($entry['methods'] ?? null)
                || ! is_array($entry['middleware'] ?? [])
                || ! is_bool($entry['documented'] ?? null)
                || ! is_array($entry['schema'] ?? null)
                || ! is_string($entry['action'] ?? null) && ($entry['action'] ?? null) !== null
            ) {
                return null;
            }

            $attribute = $entry['attribute'] ?? null;

            if ($attribute !== null && ! is_string($attribute)) {
                return null;
            }

            $methods = [];

            foreach ($entry['methods'] as $method) {
                if (! is_string($method)) {
                    return null;
                }

                $methods[] = $method;
            }

            $middleware = [];

            foreach ($entry['middleware'] ?? [] as $name) {
                if (! is_string($name)) {
                    return null;
                }

                $middleware[] = $name;
            }

            $schema = [];

            foreach ($entry['schema'] as $key => $value) {
                $schema[(string) $key] = $value;
            }

            $entries[] = [
                'uri' => $entry['uri'],
                'methods' => $methods,
                'action' => $entry['action'] ?? null,
                'middleware' => $middleware,
                'documented' => $entry['documented'],
                'attribute' => $attribute,
                'schema' => $schema,
            ];
        }

        return $entries;
    }
}
