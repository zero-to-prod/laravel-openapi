<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi\Internal\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Override;
use ZeroToProd\LaravelOpenapi\Internal\SchemaCoverage;
use ZeroToProd\LaravelOpenapi\SchemaGenerator;

/**
 * @internal
 *
 * @phpstan-type Entry array{uri: string, methods: list<string>, action: string|null, documented: bool, schema: array{paths?: array<string, mixed>, components?: array<string, mixed>}}
 */
class Status extends Tool
{
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

        $entries = array_values(array_filter(
            $SchemaGenerator->inventory(),
            static fn (array $entry): bool => $prefix === null || str_starts_with($entry['uri'], $prefix),
        ));

        // The suite that records coverage runs in a different process to this
        // one, so the file it left behind is the only record available here.
        SchemaCoverage::load();

        $document = ['paths' => array_replace_recursive(
            [],
            ...array_map(static fn (array $entry): array => $entry['schema']['paths'] ?? [], $entries),
        )];

        return Response::text($this->render(
            $prefix,
            $entries,
            SchemaCoverage::declared($document),
            SchemaCoverage::missing($document),
        ));
    }

    /**
     * @param  list<Entry>  $entries
     * @param  list<string>  $declared
     * @param  list<string>  $missing
     */
    private function render(?string $prefix, array $entries, array $declared, array $missing): string
    {
        $closures = array_values(array_filter($entries, static fn (array $entry): bool => $entry['action'] === null));
        $undocumented = array_values(array_filter(
            $entries,
            static fn (array $entry): bool => $entry['action'] !== null && ! $entry['documented'],
        ));

        $documented = count($entries) - count($closures) - count($undocumented);

        $sections = [
            '# Schema status',
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
        ];

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
