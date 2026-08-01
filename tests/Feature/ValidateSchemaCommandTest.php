<?php

declare(strict_types=1);

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use ZeroToProd\LaravelOpenapi\SchemaGenerator;
use ZeroToProd\LaravelOpenapi\Tests\Fixtures\DanglingReferenceController;
use ZeroToProd\LaravelOpenapi\Tests\Fixtures\MissingResponsesController;

/**
 * `expectsOutputToContain` matches individual writes, and the errors are
 * rendered as a single bullet list, so assert against the whole output.
 */
function validateSchema(): array
{
    return [Artisan::call('openapi:validate'), Artisan::output()];
}

it('passes for the document the package generates', function (): void {
    [$status, $output] = validateSchema();

    expect($status)->toBe(0)
        ->and($output)->toContain('valid OpenAPI 3.0.4 document (1 paths)');
});

it('fails when an operation omits a required field', function (): void {
    Route::get('missing-responses', MissingResponsesController::class);

    [$status, $output] = validateSchema();

    expect($status)->toBe(1)
        ->and($output)->toContain('Operation is missing required property: responses');
});

it('fails when a reference does not resolve', function (): void {
    Route::get('dangling-reference', DanglingReferenceController::class);

    [$status, $output] = validateSchema();

    expect($status)->toBe(1)
        ->and($output)->toContain('#/components/schemas/DoesNotExist');
});

it('fails when the generated document cannot be read', function (): void {
    // Invalid UTF-8 in a document-level field, so the json_encode() feeding the
    // reader throws before any specification exists to validate.
    app()->instance(SchemaGenerator::class, new SchemaGenerator(
        app(Router::class),
        ['openapi' => "\xB1\x31"],
    ));

    [$status, $output] = validateSchema();

    expect($status)->toBe(1)
        ->and($output)->toContain('could not be read');
});

it('reports every error at once rather than stopping at the first', function (): void {
    Route::get('missing-responses', MissingResponsesController::class);
    Route::get('dangling-reference', DanglingReferenceController::class);

    [$status, $output] = validateSchema();

    expect($status)->toBe(1)
        ->and($output)->toContain('#/components/schemas/DoesNotExist')
        ->and($output)->toContain('Operation is missing required property: responses');
});
