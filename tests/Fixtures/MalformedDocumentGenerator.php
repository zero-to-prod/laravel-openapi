<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi\Tests\Fixtures;

use Override;
use ZeroToProd\LaravelOpenapi\SchemaGenerator;

/**
 * A path whose value is not a Path Item, and an operation whose `security` is a
 * bare string rather than a requirement object.
 *
 * These arrive from a generator rather than an `#[ApiSchema]` attribute because
 * the attribute's own type shape rejects them — which is the point: the only
 * way to reach the validator with a document this broken is to hand-build it,
 * and the validator is the one command that has to survive being handed one.
 */
readonly class MalformedDocumentGenerator extends SchemaGenerator
{
    /** @return array<string, mixed> */
    #[Override]
    public function document(): array
    {
        return [
            'openapi' => '3.0.4',
            'info' => ['title' => 'Test', 'version' => '1.0.0'],
            'paths' => [
                '/not-a-path-item' => 'not a path item',
                '/bare-string-security' => [
                    'get' => [
                        'operationId' => 'bareStringSecurity',
                        'security' => ['not-a-requirement-object'],
                        'responses' => ['200' => ['description' => 'Anything.']],
                    ],
                ],
            ],
        ];
    }
}
