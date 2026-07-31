<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi\Testing;

use League\OpenAPIValidation\PSR7\OperationAddress;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use ZeroToProd\LaravelOpenapi\SchemaGenerator;

/**
 * @internal
 * Validates a request/response pair against the generated document, so the
 * schema is checked against behavior rather than against itself.
 */
class SchemaValidator
{
    /**
     * Building a validator parses the whole document, so they are reused. The
     * key is the document itself: a test that registers new routes changes the
     * document and gets its own validator.
     *
     * @var array<string, ValidatorBuilder>
     */
    private static array $builders = [];

    public function __construct(private readonly SchemaGenerator $generator)
    {
    }

    /**
     * Throws League\OpenAPIValidation\PSR7\Exception\ValidationFailed when the
     * request or response contradicts the document.
     */
    public function validate(Request $request, Response $response): OperationAddress
    {
        $builder = $this->builder();
        $factory = new PsrHttpFactory;

        $address = $builder->getServerRequestValidator()->validate($factory->createRequest($request));

        $builder->getResponseValidator()->validate($address, $factory->createResponse($response));

        SchemaCoverage::record($address->path(), $address->method(), $response->getStatusCode());

        return $address;
    }

    private function builder(): ValidatorBuilder
    {
        $json = json_encode($this->generator->document(), JSON_THROW_ON_ERROR);

        return self::$builders[hash('xxh128', $json)] ??= (new ValidatorBuilder)->fromJson($json);
    }
}
