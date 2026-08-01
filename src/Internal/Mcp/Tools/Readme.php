<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi\Internal\Mcp\Tools;

use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/** @internal */
class Readme extends Tool
{
    protected string $description = 'Read the zero-to-prod/laravel-openapi README, which documents the #[ApiSchema] attribute, the generated document, the openapi:validate and openapi:coverage commands, and the ValidatesSchema test trait.';

    public function handle(): Response
    {
        $path = self::path();

        // Unreachable here: the README ships with the package, so these guards
        // only fire against a truncated or unreadable install.
        // @codeCoverageIgnoreStart
        if (! is_file($path)) {
            return Response::error(sprintf('The README could not be found at %s.', $path));
        }
        // @codeCoverageIgnoreEnd

        $contents = file_get_contents($path);

        // @codeCoverageIgnoreStart
        if ($contents === false) {
            return Response::error(sprintf('The README at %s could not be read.', $path));
        }
        // @codeCoverageIgnoreEnd

        return Response::text($contents);
    }

    public static function path(): string
    {
        return dirname(__DIR__, 4).'/README.md';
    }
}
