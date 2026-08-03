<?php

declare(strict_types=1);

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use ZeroToProd\LaravelOpenapi\SchemaGenerator;
use ZeroToProd\LaravelOpenapi\Tests\Fixtures\DanglingReferenceController;
use ZeroToProd\LaravelOpenapi\Tests\Fixtures\DeclaredSecuritySchemeController;
use ZeroToProd\LaravelOpenapi\Tests\Fixtures\MalformedDocumentGenerator;
use ZeroToProd\LaravelOpenapi\Tests\Fixtures\MissingResponsesController;
use ZeroToProd\LaravelOpenapi\Tests\Fixtures\PathLevelParametersController;
use ZeroToProd\LaravelOpenapi\Tests\Fixtures\SecondUndeclaredSecurityController;
use ZeroToProd\LaravelOpenapi\Tests\Fixtures\StringPathsGenerator;
use ZeroToProd\LaravelOpenapi\Tests\Fixtures\UndeclaredSecuritySchemeController;

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

it('fails when an operation requires a security scheme nothing declares', function (): void {
    Route::get('undeclared-security', UndeclaredSecuritySchemeController::class);

    [$status, $output] = validateSchema();

    expect($status)->toBe(1)
        ->and($output)->toContain("Mentioned security scheme 'sanctum' not found in the given spec")
        ->and($output)->toContain('referenced by get /undeclared-security');
});

it('passes when the scheme is declared, and ignores the optional-auth idiom', function (): void {
    Route::get('declared-security', DeclaredSecuritySchemeController::class);

    [$status, $output] = validateSchema();

    expect($status)->toBe(0)
        ->and($output)->toContain('valid OpenAPI 3.0.4 document (2 paths)');
});

it('fails when a document-level security requirement names nothing declared', function (): void {
    app()->instance(SchemaGenerator::class, new SchemaGenerator(
        app(Router::class),
        [...config('openapi.openapi'), 'security' => [['oauth' => []]]],
    ));

    [$status, $output] = validateSchema();

    expect($status)->toBe(1)
        ->and($output)->toContain("Mentioned security scheme 'oauth' not found in the given spec")
        ->and($output)->toContain('referenced by the document-level security requirement');
});

it('names every operation that references a missing scheme, not just the first', function (): void {
    Route::get('undeclared-security', UndeclaredSecuritySchemeController::class);
    Route::get('also-undeclared', SecondUndeclaredSecurityController::class);

    [$status, $output] = validateSchema();

    expect($status)->toBe(1)
        ->and($output)->toContain('referenced by get /undeclared-security')
        ->and($output)->toContain('referenced by post /also-undeclared');
});

it('survives a path item and a security entry that are the wrong type entirely', function (): void {
    app()->instance(SchemaGenerator::class, new MalformedDocumentGenerator(app(Router::class)));

    [$status, $output] = validateSchema();

    // cebe has its own opinion about the nonsense and is welcome to it. What
    // the security check must not do is treat a bare string as a scheme name
    // and invent an error of its own about it, or fatal walking past it.
    expect($status)->toBe(1)
        ->and($output)->toContain('could not be read')
        ->and($output)->not->toContain("Mentioned security scheme 'not-a-requirement-object'");
});

it('survives a document whose paths is not an object at all', function (): void {
    app()->instance(SchemaGenerator::class, new StringPathsGenerator(app(Router::class)));

    [$status, $output] = validateSchema();

    expect($status)->toBe(1)->and($output)->not->toBe('');
});

it('does not mistake a path item parameters key for an operation', function (): void {
    Route::get('path-level-parameters', PathLevelParametersController::class);

    [$status, $output] = validateSchema();

    expect($status)->toBe(0)
        ->and($output)->toContain('valid OpenAPI 3.0.4 document');
});
