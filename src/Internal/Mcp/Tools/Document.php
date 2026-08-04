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
use ZeroToProd\LaravelOpenapi\SchemaGenerator;

/** @internal */
class Document extends Tool
{
    private const string STALE = <<<'MARKDOWN'
        !! stale: no fresh process, so this reflects the application as it was when
           the MCP server started. Restart it, or run `php artisan openapi:inventory --document`.
        MARKDOWN;

    protected string $name = 'document';

    protected string $description = 'The merged OpenAPI document, exactly as it is served. Whole document by default; `section` for one top-level key.';

    /** @return array<string, mixed> */
    #[Override]
    public function schema(JsonSchema $schema): array
    {
        return [
            'section' => $schema->string()->description(
                'A top-level key to return on its own, such as paths, components or info. Omit for the whole document.'
            ),
        ];
    }

    /** @throws JsonException */
    public function handle(Request $request, SchemaGenerator $SchemaGenerator): Response
    {
        $fresh = Inventory::document();
        $document = $fresh ?? $SchemaGenerator->document();

        $section = $request->get('section');
        $section = is_string($section) && $section !== '' ? $section : null;

        if ($section !== null && ! array_key_exists($section, $document)) {
            return Response::text(implode("\n", [
                sprintf('The document declares no `%s`.', $section),
                'keys: '.implode(', ', array_keys($document)),
            ]));
        }

        return Response::text(implode("\n\n", array_filter([
            $fresh === null ? self::STALE : '',
            json_encode(
                $section === null ? $document : $document[$section],
                JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
            ),
        ])));
    }
}
