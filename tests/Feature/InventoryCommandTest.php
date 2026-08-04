<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use ZeroToProd\LaravelOpenapi\ApiSchema;
use ZeroToProd\LaravelOpenapi\Tests\Fixtures\ClosureMiddlewareController;
use ZeroToProd\LaravelOpenapi\Tests\Fixtures\EnumConstructedController;
use ZeroToProd\LaravelOpenapi\Tests\Fixtures\EnumConstructedSchema;
use ZeroToProd\LaravelOpenapi\Tests\Fixtures\ShowArticleController;
use ZeroToProd\LaravelOpenapi\Tests\Fixtures\SubApiSchema;
use ZeroToProd\LaravelOpenapi\Tests\Fixtures\SubclassSchemaController;
use ZeroToProd\LaravelOpenapi\Tests\Fixtures\UnbuildableController;
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
        ->and($article[0]['attribute'])->toBe(ApiSchema::class)
        ->and($article[0]['schema']['paths'])->toHaveKey('/articles/{id}');
});

it('carries every key the status tool insists on', function (): void {
    [, $output] = inventory(['--json' => true]);

    $decoded = json_decode(trim($output), true, 512, JSON_THROW_ON_ERROR);

    expect($decoded[0])->toHaveKeys(['uri', 'methods', 'action', 'middleware', 'documented', 'attribute', 'schema']);
});

it('reports the middleware a route runs, group middleware resolved and parameters kept', function (): void {
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('api/messages', UndocumentedController::class)->middleware('throttle:api');
    });

    [, $output] = inventory(['--json' => true]);

    $decoded = json_decode(trim($output), true, 512, JSON_THROW_ON_ERROR);

    $middleware = array_column($decoded, 'middleware', 'uri');

    expect($middleware['/api/messages'])->toBe(['auth:sanctum', 'throttle:api'])
        ->and($middleware['/openapi.json'])->toBe(['api']);
});

it('costs one route its controller middleware, not the whole inventory, when it will not build', function (): void {
    Route::middleware('auth:sanctum')->post('api/unbuildable', UnbuildableController::class);

    [$status, $output] = inventory(['--json' => true]);

    $decoded = json_decode(trim($output), true, 512, JSON_THROW_ON_ERROR);

    expect($status)->toBe(0)
        ->and(array_column($decoded, 'middleware', 'uri')['/api/unbuildable'])->toBe(['auth:sanctum']);
});

it('reports no middleware for one the route cannot name, rather than failing to encode it', function (): void {
    Route::post('api/messages', ClosureMiddlewareController::class);

    [, $output] = inventory(['--json' => true]);

    $decoded = json_decode(trim($output), true, 512, JSON_THROW_ON_ERROR);

    expect(array_column($decoded, 'middleware', 'uri')['/api/messages'])->toBe([]);
});

it('names the concrete subclass, not the attribute the generator matched on', function (): void {
    Route::get('sub', SubclassSchemaController::class);
    Route::get('enum-constructed', EnumConstructedController::class);

    [, $output] = inventory(['--json' => true]);

    $decoded = json_decode(trim($output), true, 512, JSON_THROW_ON_ERROR);

    $attributes = array_column($decoded, 'attribute', 'uri');

    expect($attributes['/sub'])->toBe(SubApiSchema::class)
        ->and($attributes['/enum-constructed'])->toBe(EnumConstructedSchema::class);
});

it('reports a null attribute for a route that declares nothing', function (): void {
    Route::post('articles/{id}/publish', UndocumentedController::class);
    Route::get('health', fn (): string => 'ok');

    [, $output] = inventory(['--json' => true]);

    $decoded = json_decode(trim($output), true, 512, JSON_THROW_ON_ERROR);

    $attributes = array_column($decoded, 'attribute', 'uri');

    expect($attributes['/articles/{id}/publish'])->toBeNull()
        ->and($attributes['/health'])->toBeNull();
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

it('emits the merged document as one line of JSON, for the tools that report it', function (): void {
    Route::get('articles/{id}', ShowArticleController::class);

    [$status, $output] = inventory(['--document' => true]);

    $lines = array_values(array_filter(explode("\n", trim($output))));

    expect($status)->toBe(0)->and($lines)->toHaveCount(1);

    $document = json_decode($lines[0], true, 512, JSON_THROW_ON_ERROR);

    expect($document)->toHaveKeys(['openapi', 'info', 'paths'])
        ->and($document['paths'])->toHaveKey('/articles/{id}')
        ->and($document['paths']['/articles/{id}']['get']['operationId'])->toBe('showArticle');
});
