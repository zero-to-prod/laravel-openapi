<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi\Internal;

/**
 * @internal
 *
 * @phpstan-import-type Entry from Inventory
 */
final class DeclaredPaths
{
    public const string SKIPPED = 'Declared paths were not checked against the routes they annotate: a server URL carries a {variable}, so the base they resolve against is not known statically.';

    private const array operations = ['get', 'put', 'post', 'delete', 'options', 'head', 'patch', 'trace'];

    /**
     * @param  list<Entry>  $entries
     * @param  list<mixed>  $servers  The document's `servers`, which declared paths resolve against.
     * @return array{errors: list<string>, skipped: string|null}
     */
    public static function check(array $entries, array $servers): array
    {
        $bases = self::bases($servers);

        if ($bases === null) {
            return ['errors' => [], 'skipped' => self::SKIPPED];
        }

        $errors = [];

        foreach ($entries as $entry) {
            $error = self::error($entry, $bases);

            if ($error !== null) {
                $errors[] = $error;
            }
        }

        return ['errors' => $errors, 'skipped' => null];
    }

    /**
     * @param  list<mixed>  $servers
     * @return list<string>|null
     */
    private static function bases(array $servers): ?array
    {
        $bases = [];

        foreach ($servers as $server) {
            $url = is_array($server) ? $server['url'] ?? null : null;

            if (! is_string($url)) {
                continue;
            }

            if (str_contains($url, '{')) {
                return null;
            }

            $path = parse_url($url, PHP_URL_PATH);
            $path = is_string($path) ? trim($path, '/') : '';

            $bases[] = $path === '' ? '' : '/'.$path;
        }

        return $bases === [] ? [''] : array_values(array_unique($bases));
    }

    /**
     * @param  Entry  $entry
     * @param  list<string>  $bases
     */
    private static function error(array $entry, array $bases): ?string
    {
        if (! $entry['documented'] || $entry['action'] === null) {
            return null;
        }

        $paths = $entry['schema']['paths'] ?? null;

        if (! is_array($paths) || $paths === []) {
            return null;
        }

        $uri = self::placeholders($entry['uri']);
        $methods = array_map(strtolower(...), $entry['methods']);

        $declared = [];
        $wrongMethod = [];

        foreach ($paths as $path => $item) {
            $path = (string) $path;
            $declared[] = $path;
            $operations = is_array($item) ? array_values(array_intersect(self::operations, array_keys($item))) : [];

            foreach ($bases as $base) {
                if (self::placeholders($base.$path) !== $uri) {
                    continue;
                }

                if ($operations === [] || array_intersect($methods, $operations) !== []) {
                    return null;
                }

                foreach ($operations as $operation) {
                    $wrongMethod[] = $operation.' '.$path;
                }
            }
        }

        return $wrongMethod === []
            ? self::pathError($entry, $declared, $bases)
            : self::message($entry, $wrongMethod);
    }

    private static function placeholders(string $path): string
    {
        return preg_replace('/\{([^:?}]*)[^}]*}/', '{$1}', $path) ?? $path;
    }

    /**
     * @param  Entry  $entry
     * @param  list<string>  $declared
     * @param  list<string>  $bases
     */
    private static function pathError(array $entry, array $declared, array $bases): string
    {
        if ($bases === ['']) {
            return self::message($entry, $declared);
        }

        $resolved = [];

        foreach ($bases as $base) {
            foreach ($declared as $path) {
                $resolved[] = $base.$path;
            }
        }

        return self::message($entry, $declared, sprintf(
            ', which resolves to [%s] against the server base%s [%s]',
            implode(', ', array_unique($resolved)),
            count($bases) === 1 ? '' : 's',
            implode(', ', $bases),
        ));
    }

    /**
     * @param  Entry  $entry
     * @param  list<string>  $declared
     */
    private static function message(array $entry, array $declared, string $resolution = ''): string
    {
        return sprintf(
            'The attribute on %s declares [%s]%s but the route it annotates is [%s %s].',
            $entry['action'],
            implode(', ', $declared),
            $resolution === '' ? '' : $resolution.',',
            implode('|', $entry['methods']),
            $entry['uri'],
        );
    }
}
