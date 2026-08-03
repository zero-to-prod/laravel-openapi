<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi\Internal\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Override;
use Symfony\Component\Process\Process;
use Throwable;
use ZeroToProd\LaravelOpenapi\Internal\SchemaCoverage;
use ZeroToProd\LaravelOpenapi\SchemaGenerator;

/**
 * @internal
 *
 * @phpstan-type Entry array{uri: string, methods: list<string>, action: string|null, documented: bool, schema: array<string, mixed>}
 */
class Status extends Tool
{
    /**
     * A boot costs a second or two. This tool is called twice a session — once
     * to plan and once to verify — and the verification call is worthless
     * without it, so the trade is not close.
     */
    private const int TIMEOUT = 30;

    private const string STALE = <<<'MARKDOWN'
        !! Could not read the application from a fresh process, so what follows
           reflects it as it was when this MCP server started. Attributes added
           or edited since then are invisible here, and so are new routes.
           Restart the server, or run `php artisan openapi:inventory` in a shell.
        MARKDOWN;

    protected string $name = 'status';

    protected string $description = 'Reports which registered routes declare no schema, and which declared responses no test has exercised. Call it to plan the work, and again to confirm the work is done.';

    /** @return array<string, mixed> */
    #[Override]
    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema->string()->description(
                'Report only routes whose URI starts with this prefix, such as /api. Omit it to report every registered route.'
            ),
        ];
    }

    public function handle(Request $request, SchemaGenerator $SchemaGenerator): Response
    {
        $path = $request->get('path');
        $prefix = is_string($path) && trim($path, '/') !== '' ? '/'.trim($path, '/') : null;

        $fresh = $this->fromFreshProcess();

        $entries = array_values(array_filter(
            $fresh ?? $SchemaGenerator->inventory(),
            static fn (array $entry): bool => $prefix === null || str_starts_with($entry['uri'], $prefix),
        ));

        // The suite that records coverage runs in a different process to this
        // one, so the file it left behind is the only record available here.
        // Flush first: a `--reset` between calls has to be visible, and load()
        // merges rather than replaces.
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
     * Reflection cannot see a controller edited after the class was
     * autoloaded, and this server holds the application open for its whole
     * life — so the first call is what makes every later one wrong. Reading
     * the inventory out of a process that started a moment ago is the only
     * answer that survives the agent editing the files it just asked about.
     *
     * @return list<Entry>|null Null when the subprocess could not be trusted.
     */
    private function fromFreshProcess(): ?array
    {
        $artisan = base_path('artisan');

        if (! is_file($artisan)) {
            return null;
        }

        $Process = new Process([PHP_BINARY, $artisan, 'openapi:inventory', '--json'], base_path());
        $Process->setTimeout(self::TIMEOUT);

        // @codeCoverageIgnoreStart
        // Only reachable where the runtime refuses to fork at all — `proc_open`
        // disabled by a hardened php.ini, typically. The suite cannot produce
        // that, but a report with a staleness warning still beats a stack trace.
        try {
            $Process->run();
        } catch (Throwable) {
            return null;
        }
        // @codeCoverageIgnoreEnd

        return $Process->isSuccessful() ? $this->decode($Process->getOutput()) : null;
    }

    /**
     * The inventory is one line of JSON, printed last. Scanning backwards means
     * a deprecation notice or a stray `dump()` earlier in the output costs
     * nothing.
     *
     * @return list<Entry>|null
     */
    private function decode(string $output): ?array
    {
        foreach (array_reverse(preg_split('/\R/', trim($output)) ?: []) as $line) {
            $decoded = json_decode(trim($line), true);

            if (is_array($decoded)) {
                return $this->entries($decoded);
            }
        }

        return null;
    }

    /**
     * A half-understood inventory is worse than none: it would report real
     * routes as undocumented. Anything unexpected fails the whole batch.
     *
     * @param  array<mixed>  $decoded
     * @return list<Entry>|null
     */
    private function entries(array $decoded): ?array
    {
        $entries = [];

        foreach ($decoded as $entry) {
            if (! is_array($entry)
                || ! is_string($entry['uri'] ?? null)
                || ! is_array($entry['methods'] ?? null)
                || ! is_bool($entry['documented'] ?? null)
                || ! is_array($entry['schema'] ?? null)
                || ! is_string($entry['action'] ?? null) && ($entry['action'] ?? null) !== null
            ) {
                return null;
            }

            $methods = [];

            foreach ($entry['methods'] as $method) {
                if (! is_string($method)) {
                    return null;
                }

                $methods[] = $method;
            }

            $schema = [];

            foreach ($entry['schema'] as $key => $value) {
                $schema[(string) $key] = $value;
            }

            $entries[] = [
                'uri' => $entry['uri'],
                'methods' => $methods,
                'action' => $entry['action'] ?? null,
                'documented' => $entry['documented'],
                'schema' => $schema,
            ];
        }

        return $entries;
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

        $sections = array_values(array_filter([
            '# Schema status',
            $stale ? self::STALE : '',
            implode("\n", array_filter([
                $prefix === null ? '' : sprintf('Scope: routes under %s.', $prefix),
                sprintf(
                    'Routes: %d in scope, %d documented, %d undocumented.',
                    count($entries),
                    $documented,
                    count($undocumented),
                ),
                sprintf('Responses: %d declared, %d never exercised.', count($declared), count($missing)),
            ])),
        ]));

        if ($entries === []) {
            $sections[] = $prefix === null
                ? 'No routes are registered at all, so there is nothing to document.'
                : sprintf('No registered route starts with %s. Check the prefix, or omit it to see every route.', $prefix);

            return $this->join($sections);
        }

        if ($undocumented !== []) {
            $sections[] = $this->section(
                sprintf('## Undocumented routes (%d)', count($undocumented)),
                'Add an #[ApiSchema] attribute to each method below. Call the `example` tool for the shape it takes.',
                array_map(
                    static fn (array $entry): string => sprintf(
                        '%s %s — %s',
                        implode('|', $entry['methods']),
                        $entry['uri'],
                        $entry['action'],
                    ),
                    $undocumented,
                ),
            );
        }

        if ($missing !== []) {
            $sections[] = $this->section(
                sprintf('## Declared responses no test exercised (%d)', count($missing)),
                SchemaCoverage::exercised() === []
                    ? sprintf(
                        'No coverage is recorded at all, so this lists every declared response rather than the gaps. Run the test suite first: it writes %s, which this tool reads.',
                        SchemaCoverage::path(),
                    )
                    : 'Each one needs a test that passes the response through assertMatchesSchema(). Until then `openapi:coverage` fails.',
                $missing,
            );
        }

        if ($closures !== []) {
            $sections[] = $this->section(
                sprintf('## Routes that cannot be documented (%d)', count($closures)),
                'A closure cannot carry an attribute. Move each to a controller method to document it.',
                array_map(
                    static fn (array $entry): string => implode('|', $entry['methods']).' '.$entry['uri'],
                    $closures,
                ),
            );
        }

        if ($undocumented === [] && $missing === [] && $declared !== []) {
            $sections[] = 'Every route in scope declares a schema, and every response it declares was exercised.';
        }

        if ($declared === []) {
            $sections[] = 'Nothing in scope declares a response yet, so there is no coverage to report.';
        }

        return $this->join($sections);
    }

    /** @param  list<string>  $items */
    private function section(string $heading, string $instruction, array $items): string
    {
        return $this->join([
            $heading,
            $instruction,
            implode("\n", array_map(static fn (string $item): string => '- '.$item, $items)),
        ]);
    }

    /** @param  list<string>  $parts */
    private function join(array $parts): string
    {
        return implode("\n\n", $parts);
    }
}
