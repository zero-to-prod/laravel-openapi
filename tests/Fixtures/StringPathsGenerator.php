<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi\Tests\Fixtures;

use Override;
use ZeroToProd\LaravelOpenapi\SchemaGenerator;

/**
 * Serves a document whose `paths` is not an object. Nothing the package builds
 * can produce this, but the validator is the one command that has to cope with
 * a document being wrong in any way at all.
 */
readonly class StringPathsGenerator extends SchemaGenerator
{
    /** @return array<string, mixed> */
    #[Override]
    public function document(): array
    {
        return [
            'openapi' => '3.0.4',
            'info' => ['title' => 'Test', 'version' => '1.0.0'],
            'paths' => 'not an object',
        ];
    }
}
