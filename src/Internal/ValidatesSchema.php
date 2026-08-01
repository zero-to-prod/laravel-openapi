<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi\Internal;

use Illuminate\Testing\TestResponse;
use JsonException;
use League\OpenAPIValidation\PSR7\Exception\ValidationFailed;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;
use ZeroToProd\LaravelOpenapi\SchemaGenerator;

/** @internal */
trait ValidatesSchema
{
    /**
     * @template TResponse of SymfonyResponse
     *
     * @param  TestResponse<TResponse>  $response
     * @return TestResponse<TResponse>
     *
     * @throws JsonException
     */
    protected function assertMatchesSchema(TestResponse $response): TestResponse
    {
        if (! class_exists(ValidatorBuilder::class)) {
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

        $this->addToAssertionCount(1);

        return $response;
    }

    protected function assertSchemaFullyExercised(): void
    {
        $missing = SchemaCoverage::missing(app(SchemaGenerator::class)->document());

        Assert::assertSame([], $missing, sprintf(
            "The document declares %d response(s) that no test exercised:\n  - %s",
            count($missing),
            implode("\n  - ", $missing),
        ));
    }

    private function describeValidationFailure(Throwable $e): string
    {
        $lines = [$e->getMessage()];

        while (($e = $e->getPrevious()) instanceof Throwable) {
            $lines[] = 'caused by: '.$e->getMessage();
        }

        return implode(PHP_EOL.'  ', $lines);
    }
}
