<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi\Testing;

use Illuminate\Testing\TestResponse;
use League\OpenAPIValidation\PSR7\Exception\ValidationFailed;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use PHPUnit\Framework\Assert;
use Throwable;
use ZeroToProd\LaravelOpenapi\SchemaGenerator;

trait ValidatesSchema
{
    /**
     * Assert that a response, and the request that produced it, match what the
     * #[ApiSchema] attributes declare. Records the operation as exercised.
     */
    protected function assertMatchesSchema(TestResponse $response): TestResponse
    {
        if (!class_exists(ValidatorBuilder::class)) {
            Assert::fail(
                'Matching responses against the schema requires league/openapi-psr7-validator. '
                .'Install it with `composer require --dev league/openapi-psr7-validator symfony/psr-http-message-bridge nyholm/psr7`.'
            );
        }

        if ($response->baseRequest === null) {
            Assert::fail('The response was not produced by an HTTP test request, so no operation can be resolved.');
        }

        try {
            app(SchemaValidator::class)->validate($response->baseRequest, $response->baseResponse);
        } catch (ValidationFailed $e) {
            Assert::fail($this->describeValidationFailure($e));
        }

        Assert::assertTrue(true);

        return $response;
    }

    /**
     * Assert that every response the document declares was exercised by a call
     * to assertMatchesSchema(). Declared-but-unexercised operations are
     * unverified claims.
     *
     * Coverage accumulates in static state, so this belongs in a test that runs
     * after the rest of the suite, in a single process.
     */
    protected function assertSchemaFullyExercised(): void
    {
        $missing = SchemaCoverage::missing(app(SchemaGenerator::class)->document());

        Assert::assertSame([], $missing, sprintf(
            "The document declares %d response(s) that no test exercised:\n  - %s",
            count($missing),
            implode("\n  - ", $missing),
        ));
    }

    /**
     * The useful detail is in the exception chain: the outer message names the
     * operation, the inner one names the keyword that failed.
     */
    private function describeValidationFailure(Throwable $e): string
    {
        $lines = [$e->getMessage()];

        while (($e = $e->getPrevious()) !== null) {
            $lines[] = 'caused by: '.$e->getMessage();
        }

        return implode(PHP_EOL.'  ', $lines);
    }
}
