<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi\Internal;

/**
 * Checks that each attribute declares the path of the route it annotates.
 *
 * Nothing else does, and the checks that exist point somewhere other than the
 * declaration: cebe reads the document on its own terms and never sees a route,
 * league reports the concrete path a request could not match rather than the
 * template one character away from matching it, and the coverage gate blames
 * the responses of an operation no request could reach. So a placeholder
 * renamed between the route and the attribute costs a hunt for a missing
 * declaration that is in fact right there.
 *
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
     * The path component of each server URL, which is the only part a declared
     * path resolves against. Null when a URL carries a `{variable}`: the base is
     * not knowable statically, and a guess at it invents mismatches.
     *
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

        // No server at all says what a single server at the root says, which is
        // the OpenAPI default: declared paths are the paths themselves.
        return $bases === [] ? [''] : array_values(array_unique($bases));
    }

    /**
     * @param  Entry  $entry
     * @param  list<string>  $bases
     */
    private static function error(array $entry, array $bases): ?string
    {
        // A route with no attribute declares nothing to be wrong about, and a
        // closure route cannot carry one.
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

                // A path item declaring no operation at all has no method to
                // disagree with the route about, so the path matching is enough.
                if ($operations === [] || array_intersect($methods, $operations) !== []) {
                    return null;
                }

                foreach ($operations as $operation) {
                    $wrongMethod[] = $operation.' '.$path;
                }
            }
        }

        // An attribute may legitimately declare several paths, so this reports
        // only when none of them is the route it sits on.
        return $wrongMethod === []
            ? self::pathError($entry, $declared, $bases)
            : self::message($entry, $wrongMethod);
    }

    /**
     * `{id?}` and `{user:slug}` both bind the parameter `{id}` and `{user}`
     * name, and a declared path spells neither suffix. Comparing the names is
     * the point: `{id}` against `{message_id}` is the bug being looked for.
     */
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
        // Against the default base the declared path and its resolved form are
        // the same string, and printing it twice reads as a contradiction. With
        // a base configured they differ, and the difference is the whole bug:
        // a document whose paths still carry the prefix its `servers` now adds.
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
