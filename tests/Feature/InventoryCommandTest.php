<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use ZeroToProd\LaravelOpenapi\Tests\Fixtures\ShowArticleController;
use ZeroToProd\LaravelOpenapi\Tests\Fixtures\UndocumentedController;

function inventory(array $options = []): array
{
    return [Artisan::call('openapi:inventory', $options), Artisan::output()];
}

it('emits the inventory as one line of JSON', function (): void {
    Route::get('articles/{id}', ShowArticleController::class);

    [$status, $output] = inventory(['--json' => true]);

    $lines = array_values(array_filter(explode("\n", trim($output))));

    expect($status)->toBe(0)->and($lines)->toHaveCount(1);

    $decoded = json_decode($lines[0], true, 512, JSON_THROW_ON_ERROR);

    $article = array_values(array_filter(
        $decoded,
        static fn (array $entry): bool => $entry['uri'] === '/articles/{id}',
    ));

    expect($article)->toHaveCount(1)
        ->and($article[0]['methods'])->toBe(['GET'])
        ->and($article[0]['action'])->toBe(ShowArticleController::class.'::__invoke')
        ->and($article[0]['documented'])->toBeTrue()
        ->and($article[0]['schema']['paths'])->toHaveKey('/articles/{id}');
});

it('carries every key the status tool insists on', function (): void {
    [, $output] = inventory(['--json' => true]);

    $decoded = json_decode(trim($output), true, 512, JSON_THROW_ON_ERROR);

    expect($decoded[0])->toHaveKeys(['uri', 'methods', 'action', 'documented', 'schema']);
});

it('reports a closure route with a null action rather than omitting it', function (): void {
    Route::get('health', fn (): string => 'ok');

    [, $output] = inventory(['--json' => true]);

    $decoded = json_decode(trim($output), true, 512, JSON_THROW_ON_ERROR);

    $health = array_values(array_filter(
        $decoded,
        static fn (array $entry): bool => $entry['uri'] === '/health',
    ));

    expect($health)->toHaveCount(1)->and($health[0]['action'])->toBeNull();
});

it('prints a readable listing when asked for no particular format', function (): void {
    Route::post('articles/{id}/publish', UndocumentedController::class);
    Route::get('health', fn (): string => 'ok');

    [$status, $output] = inventory();

    expect($status)->toBe(0)
        ->and($output)->toContain('[x] GET /openapi.json')
        ->and($output)->toContain('[ ] POST /articles/{id}/publish — '.UndocumentedController::class.'::__invoke')
        ->and($output)->toContain('[ ] GET /health — closure');
});
