<?php

declare(strict_types=1);

use ZeroToProd\LaravelOpenapi\Internal\Fragment;

it('renders a fragment as the PHP an attribute holds it in', function (): void {
    $rendered = Fragment::render([
        '/articles/{id}' => [
            'get' => [
                'operationId' => 'showArticle',
                'deprecated' => false,
                'security' => [['bearer' => []]],
                'responses' => [
                    200 => [
                        'description' => "It's the article.",
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'required' => ['id'],
                                    'properties' => ['id' => ['type' => 'string', 'maxLength' => 26]],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    expect($rendered)->toBe(<<<'PHP'
            '/articles/{id}' => [
                'get' => [
                    'operationId' => 'showArticle',
                    'deprecated' => false,
                    'security' => [
                        [
                            'bearer' => [],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'It\'s the article.',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => [
                                            'id',
                                        ],
                                        'properties' => [
                                            'id' => [
                                                'type' => 'string',
                                                'maxLength' => 26,
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        PHP);
});

it('quotes a status key that PHP coerced to an int on the way in', function (): void {
    expect(Fragment::render(['/x' => ['get' => ['responses' => ['200' => ['description' => 'Fine.']]]]]))
        ->toContain("'200' => [");
});

it('writes no keys for a list, which is what makes var_export output unusable as an example', function (): void {
    expect(Fragment::render(['tags' => ['articles', 'public']]))->toBe(<<<'PHP'
            'tags' => [
                'articles',
                'public',
            ],
        PHP);
});

it('escapes a value that would otherwise not parse', function (): void {
    expect(Fragment::render(['pattern' => "^\\d+'$"]))->toBe("    'pattern' => '^\\\\d+\\'\$',");
});

it('cuts a fragment long enough to cost more than reading the class it came from', function (): void {
    $responses = [];

    foreach (range(1, 30) as $index) {
        $responses[(string) (400 + $index)] = ['description' => 'A failure.'];
    }

    $rendered = Fragment::render(['/x' => ['get' => ['responses' => $responses]]]);
    $lines = explode("\n", $rendered);

    expect($lines)->toHaveCount(41)
        ->and($lines[40])->toBe('    // ... 56 more lines of the same shape, cut to keep this short.')
        ->and($rendered)->toContain("'401' => [")
        ->and($rendered)->not->toContain("'430' => [");
});
