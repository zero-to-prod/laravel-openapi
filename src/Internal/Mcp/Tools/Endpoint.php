<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi\Internal\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use JsonException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Override;
use ZeroToProd\LaravelOpenapi\Internal\Inventory;
use ZeroToProd\LaravelOpenapi\Internal\SchemaCoverage;
use ZeroToProd\LaravelOpenapi\SchemaGenerator;

/**
 * @internal
 *
 * @phpstan-import-type Entry from Inventory
 */
class Endpoint extends Tool
{
    private const array OPERATIONS = ['get', 'put', 'post', 'delete', 'options', 'head', 'patch', 'trace'];

    private const string STALE = <<<'MARKDOWN'
        !! stale: no fresh process, so this reflects the application as it was when
           the MCP server started. Restart it, or run `php artisan openapi:inventory`.
        MARKDOWN;

    protected string $name = 'endpoint';

    protected string $description = 'Everything one endpoint has: the route and its middleware, the attribute carrying it, the declared operation, the components it references, and which of its responses a test exercised.';

    /** @return array<string, mixed> */
    #[Override]
    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema->string()->description(
                'The route URI or the declared path, such as /api/messages/{id}.'
            )->required(),
            'method' => $schema->string()->description(
                'One HTTP method to report on. Omit for every method the path serves.'
            ),
        ];
    }

    /** @throws JsonException */
    public function handle(Request $request, SchemaGenerator $SchemaGenerator): Response
    {
        $path = $request->get('path');
        $path = is_string($path) && trim($path, '/') !== '' ? '/'.trim($path, '/') : null;

        if ($path === null) {
            return Response::error('`path` is required, such as /api/messages.');
        }

        $method = $request->get('method');
        $method = is_string($method) && $method !== '' ? strtoupper($method) : null;

        $fresh = Inventory::entries();
        $entries = $fresh ?? $SchemaGenerator->inventory();

        SchemaCoverage::flush();
        SchemaCoverage::load();

        return Response::text($this->render($entries, $path, $method, $fresh === null));
    }

    /**
     * @param  list<Entry>  $entries
     *
     * @throws JsonException
     */
    private function render(array $entries, string $path, ?string $method, bool $stale): string
    {
        $onPath = array_values(array_filter($entries, fn (array $entry): bool => $this->covers($entry, $path)));
        $matches = $method === null ? $onPath : $this->serving($onPath, $method);

        $sections = array_filter([
            sprintf('# %s%s', $method === null ? '' : $method.' ', $path),
            $stale ? self::STALE : '',
        ]);

        if ($matches === []) {
            return implode("\n\n", [...$sections, $this->nothing($entries, $onPath, $path, $method)]);
        }

        $components = $this->components($entries);
        $groups = [];

        foreach ($matches as $entry) {
            $declared = $this->declared($entry, $path, $method);
            $groups[json_encode([$entry['attribute'], $declared])][] = $entry;
        }

        foreach ($groups as $group) {
            $sections[] = $this->group($group, $path, $method, $components);
        }

        return implode("\n\n", $sections);
    }

    /**
     * @param  list<Entry>  $onPath
     * @return list<Entry>
     */
    private function serving(array $onPath, string $method): array
    {
        $routed = array_values(array_filter(
            $onPath,
            static fn (array $entry): bool => in_array($method, $entry['methods'], true),
        ));

        return $routed !== [] ? $routed : array_values(array_filter(
            $onPath,
            fn (array $entry): bool => $this->serves($entry, $method),
        ));
    }

    /**
     * @param  non-empty-list<Entry>  $group  Routes sharing one declaration.
     * @param  array<string, mixed>  $components
     *
     * @throws JsonException
     */
    private function group(array $group, string $path, ?string $method, array $components): string
    {
        $declared = $this->declared($group[0], $path, $method);
        $referenced = $this->referenced($declared, $components);

        return implode("\n\n", array_values(array_filter([
            implode("\n", [
                ...array_map($this->route(...), $group),
                'attribute: '.($group[0]['attribute'] ?? 'none, so this route declares nothing'),
            ]),
            $this->coverage($declared),
            $declared === [] ? '' : $this->fenced('## Declared', $declared),
            $referenced === [] ? '' : $this->fenced('## Components it references', $referenced),
        ])));
    }

    /** @param  Entry  $entry */
    private function route(array $entry): string
    {
        return sprintf(
            'route: %s %s — %s%s',
            implode('|', $entry['methods']),
            $entry['uri'],
            $entry['action'] ?? 'closure, which cannot carry an attribute',
            $entry['middleware'] === [] ? '' : ' ['.implode(', ', $entry['middleware']).']',
        );
    }

    /**
     * @param  Entry  $entry
     * @return array<string, mixed>
     */
    private function declared(array $entry, string $path, ?string $method): array
    {
        $paths = array_filter($this->paths($entry), is_array(...));
        $declared = [];

        foreach (array_key_exists($path, $paths) ? [$path] : array_keys($paths) as $key) {
            $item = $method === null ? $paths[$key] : $this->narrow($paths[$key], $method);

            if ($item !== []) {
                $declared[(string) $key] = $item;
            }
        }

        return $declared;
    }

    /**
     * @param  array<mixed>  $item
     * @return array<string, mixed>
     */
    private function narrow(array $item, string $method): array
    {
        $kept = [];

        foreach ($item as $key => $value) {
            if (! in_array(strtolower((string) $key), self::OPERATIONS, true) || strtoupper((string) $key) === $method) {
                $kept[(string) $key] = $value;
            }
        }

        return array_any(
            array_keys($kept),
            static fn (string $key): bool => in_array(strtolower($key), self::OPERATIONS, true),
        ) ? $kept : [];
    }

    /**
     * @param  array<string, mixed>  $declared
     *
     * @throws JsonException
     */
    private function coverage(array $declared): string
    {
        $document = ['paths' => $declared];
        $missing = SchemaCoverage::missing($document);
        $lines = [];

        foreach (SchemaCoverage::declared($document) as $response) {
            $lines[] = sprintf('- %s %s', $response, in_array($response, $missing, true) ? 'unexercised' : 'exercised');
        }

        if ($lines === []) {
            return 'responses: none declared';
        }

        return implode("\n", [
            sprintf(
                'responses: %d declared, %d unexercised%s',
                count($lines),
                count($missing),
                SchemaCoverage::exercised() === [] ? ' — no coverage recorded at all, so nothing counts as exercised' : '',
            ),
            ...$lines,
        ]);
    }

    /**
     * @param  array<string, mixed>  $declared
     * @param  array<string, mixed>  $components
     * @return array<string, mixed>
     */
    private function referenced(array $declared, array $components): array
    {
        $referenced = [];
        $pending = $this->refs($declared);
        $seen = [];

        while ($pending !== []) {
            $ref = array_shift($pending);

            if (isset($seen[$ref])) {
                continue;
            }

            $seen[$ref] = true;
            $segments = array_slice(explode('/', $ref), 2);
            $value = $components;

            foreach ($segments as $segment) {
                if (! is_array($value) || ! array_key_exists($segment, $value)) {
                    continue 2;
                }

                $value = $value[$segment];
            }

            $referenced = array_replace_recursive($referenced, $this->nest($segments, $value));
            $pending = [...$pending, ...$this->refs(is_array($value) ? $value : [])];
        }

        return $referenced;
    }

    /**
     * @param  array<mixed>  $value
     * @return list<string>
     */
    private function refs(array $value): array
    {
        $refs = [];

        foreach ($value as $key => $item) {
            if ($key === '$ref' && is_string($item) && str_starts_with($item, '#/components/')) {
                $refs[] = $item;
            }

            if (is_array($item)) {
                $refs = [...$refs, ...$this->refs($item)];
            }
        }

        return $refs;
    }

    /**
     * @param  list<string>  $segments
     * @return array<string, mixed>
     */
    private function nest(array $segments, mixed $value): array
    {
        foreach (array_reverse($segments) as $segment) {
            $value = [$segment => $value];
        }

        return is_array($value) ? $value : [];
    }

    /**
     * @param  list<Entry>  $entries
     * @return array<string, mixed>
     */
    private function components(array $entries): array
    {
        return array_replace_recursive([], ...array_map(
            static function (array $entry): array {
                $components = $entry['schema']['components'] ?? null;

                return is_array($components) ? $components : [];
            },
            [...$entries, []],
        ));
    }

    /**
     * @param  list<Entry>  $entries
     * @param  list<Entry>  $onPath
     */
    private function nothing(array $entries, array $onPath, string $path, ?string $method): string
    {
        if ($onPath !== []) {
            return sprintf(
                '%s is not served here. Methods on %s: %s.',
                (string) $method,
                $path,
                implode(', ', array_values(array_unique(array_merge([], ...array_map(
                    static fn (array $entry): array => $entry['methods'],
                    $onPath,
                ))))),
            );
        }

        $needle = trim($path, '/');
        $near = [];

        foreach ($entries as $entry) {
            if (str_contains($entry['uri'], $needle)) {
                $near[] = implode('|', $entry['methods']).' '.$entry['uri'];
            }
        }

        return implode("\n", array_filter([
            sprintf('No route and no declared path matches %s.', $path),
            $near === [] ? '' : "\nURIs containing it:\n".implode("\n", array_map(
                static fn (string $line): string => '- '.$line,
                $near,
            )),
        ]));
    }

    /**
     * @param  Entry  $entry
     * @return array<string, mixed>
     */
    private function paths(array $entry): array
    {
        $paths = $entry['schema']['paths'] ?? null;

        return is_array($paths) ? $paths : [];
    }

    /** @param  Entry  $entry */
    private function covers(array $entry, string $path): bool
    {
        return $entry['uri'] === $path || array_key_exists($path, $this->paths($entry));
    }

    /** @param  Entry  $entry */
    private function serves(array $entry, string $method): bool
    {
        return array_any($this->paths($entry), static fn (mixed $item): bool => is_array($item) && array_any(
            array_keys($item),
            static fn (int|string $key): bool => strtoupper((string) $key) === $method,
        ));
    }

    /**
     * @param  array<string, mixed>  $value
     *
     * @throws JsonException
     */
    private function fenced(string $heading, array $value): string
    {
        return sprintf(
            "%s\n\n```json\n%s\n```",
            $heading,
            json_encode($value, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );
    }
}
