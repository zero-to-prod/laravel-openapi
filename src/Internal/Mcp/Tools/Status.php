<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi\Internal\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
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
class Status extends Tool
{
    private const string STALE = <<<'MARKDOWN'
        !! stale: no fresh process, so this reflects the application as it was when
           the MCP server started. Restart it, or run `php artisan openapi:inventory`.
        MARKDOWN;

    protected string $name = 'status';

    protected string $description = 'Routes that declare no schema, with the middleware each runs and the attribute classes already in use; plus declared responses no test exercised.';

    /** @return array<string, mixed> */
    #[Override]
    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema->string()->description(
                'URI prefix to report on, such as /api. Omit for every registered route.'
            ),
        ];
    }

    public function handle(Request $request, SchemaGenerator $SchemaGenerator): Response
    {
        $path = $request->get('path');
        $prefix = is_string($path) && trim($path, '/') !== '' ? '/'.trim($path, '/') : null;

        $fresh = Inventory::entries();

        $entries = array_values(array_filter(
            $fresh ?? $SchemaGenerator->inventory(),
            static fn (array $entry): bool => $prefix === null || str_starts_with($entry['uri'], $prefix),
        ));

        SchemaCoverage::flush();
        SchemaCoverage::load();

        $document = ['paths' => array_replace_recursive(
            [],
            ...array_map($this->paths(...), $entries),
        )];

        return Response::text($this->render(
            $prefix,
            $entries,
            SchemaCoverage::declared($document),
            SchemaCoverage::missing($document),
            $fresh === null,
        ));
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

    /**
     * @param  list<Entry>  $entries
     * @param  list<string>  $declared
     * @param  list<string>  $missing
     */
    private function render(?string $prefix, array $entries, array $declared, array $missing, bool $stale): string
    {
        $closures = array_values(array_filter($entries, static fn (array $entry): bool => $entry['action'] === null));
        $undocumented = array_values(array_filter(
            $entries,
            static fn (array $entry): bool => $entry['action'] !== null && ! $entry['documented'],
        ));

        $documented = count($entries) - count($closures) - count($undocumented);

        $sections = array_filter([
            '# Schema status',
            $stale ? self::STALE : '',
        ]);

        if ($entries === []) {
            $sections[] = $prefix === null
                ? 'routes: 0 registered.'
                : sprintf('routes: 0 matching %s.', $prefix);

            return $this->join($sections);
        }

        $sections[] = implode("\n", array_values(array_filter([
            $prefix === null ? '' : 'scope: '.$prefix,
            sprintf(
                'routes: %d, documented %d, undocumented %d, closure %d',
                count($entries),
                $documented,
                count($undocumented),
                count($closures),
            ),
            sprintf('responses: %d declared, %d unexercised', count($declared), count($missing)),
            $this->attributes($entries),
        ])));

        if ($undocumented !== []) {
            $sections[] = $this->section(
                sprintf('## Undocumented routes (%d)', count($undocumented)),
                array_map(
                    fn (array $entry): string => sprintf(
                        '%s %s — %s%s',
                        implode('|', $entry['methods']),
                        $entry['uri'],
                        $entry['action'],
                        $this->middleware($entry),
                    ),
                    $undocumented,
                ),
            );
        }

        if ($missing !== []) {
            $sections[] = $this->section(
                sprintf(
                    '## Declared responses no test exercised (%d)%s',
                    count($missing),
                    SchemaCoverage::exercised() === []
                        ? sprintf(' — no coverage recorded at all, so this is every declared response; the suite writes %s', SchemaCoverage::path())
                        : '',
                ),
                $missing,
            );
        }

        if ($closures !== []) {
            $sections[] = $this->section(
                sprintf('## Closure routes (%d) — cannot carry an attribute', count($closures)),
                array_map(
                    static fn (array $entry): string => implode('|', $entry['methods']).' '.$entry['uri'],
                    $closures,
                ),
            );
        }

        return $this->join($sections);
    }

    /**
     * The attribute classes documented routes in scope carry, counted. Reported
     * as the state it is, without ranking one as the shape to follow: which
     * class a new route should use is a call this tool cannot make.
     *
     * @param  list<Entry>  $entries
     */
    private function attributes(array $entries): string
    {
        $counts = [];

        foreach ($entries as $entry) {
            if ($entry['documented'] && $entry['attribute'] !== null) {
                $counts[$entry['attribute']] = ($counts[$entry['attribute']] ?? 0) + 1;
            }
        }

        arsort($counts);

        $names = [];

        foreach ($counts as $class => $count) {
            $names[] = sprintf('%s (%d)', $class, $count);
        }

        return $names === [] ? '' : 'attributes in use: '.implode(', ', $names);
    }

    /** @param  Entry  $entry */
    private function middleware(array $entry): string
    {
        $names = array_map(static function (string $name): string {
            $parts = explode(':', $name, 2);

            return basename(str_replace('\\', '/', $parts[0])).(isset($parts[1]) ? ':'.$parts[1] : '');
        }, $entry['middleware']);

        return $names === [] ? '' : ' ['.implode(', ', $names).']';
    }

    /** @param  list<string>  $items */
    private function section(string $heading, array $items): string
    {
        return $this->join([
            $heading,
            implode("\n", array_map(static fn (string $item): string => '- '.$item, $items)),
        ]);
    }

    /** @param  list<string>  $parts */
    private function join(array $parts): string
    {
        return implode("\n\n", $parts);
    }
}
