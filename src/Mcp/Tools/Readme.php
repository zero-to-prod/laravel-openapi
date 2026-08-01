<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi\Mcp\Tools;

use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/** @internal */
class Readme extends Tool
{
    protected string $description = 'Read the zero-to-prod/laravel-openapi README, which documents the #[ApiSchema] attribute, the generated document, the openapi:validate and openapi:coverage commands, and the ValidatesSchema test trait.';

    public function handle(): Response
    {
        $path = self::path();

        if (! is_file($path)) {
            return Response::error(sprintf('The README could not be found at %s.', $path));
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return Response::error(sprintf('The README at %s could not be read.', $path));
        }

        return Response::text($contents);
    }

    public static function path(): string
    {
        return dirname(__DIR__, 3).'/README.md';
    }
}
