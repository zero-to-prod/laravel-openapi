<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi\Internal;

use JsonException;
use League\OpenAPIValidation\PSR7\Exception\ValidationFailed;
use League\OpenAPIValidation\PSR7\OperationAddress;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use ZeroToProd\LaravelOpenapi\SchemaGenerator;

/** @internal */
class SchemaValidator
{
    /** @var array<string, ValidatorBuilder> */
    private static array $builders = [];

    public function __construct(private readonly SchemaGenerator $SchemaGenerator) {}

    /** @throws JsonException|ValidationFailed */
    public function validate(Request $request, Response $response): OperationAddress
    {
        $builder = $this->builder();
        $factory = new PsrHttpFactory;

        $address = $builder->getServerRequestValidator()->validate($factory->createRequest($request));

        $builder->getResponseValidator()->validate($address, $factory->createResponse($response));

        SchemaCoverage::record($address->path(), $address->method(), $response->getStatusCode());

        return $address;
    }

    /** @throws JsonException */
    private function builder(): ValidatorBuilder
    {
        $json = json_encode($this->SchemaGenerator->document(), JSON_THROW_ON_ERROR);

        return self::$builders[hash('xxh128', $json)] ??= (new ValidatorBuilder)->fromJson($json);
    }
}
