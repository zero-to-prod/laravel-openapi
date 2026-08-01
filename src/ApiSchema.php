<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi;

use Attribute;
use Illuminate\Routing\Route as Registered;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use ZeroToProd\LaravelOpenapi\Http\Controllers\SchemaController;

/**
 * Declares the OpenAPI document fragment for the route handled by this method.
 *
 * The shapes below are the OpenAPI 3.0.4 object model. Schema Objects are
 * recursive and type aliases cannot be, so the recursive positions
 * (`properties`, `items`, `allOf`, `callbacks`, ...) widen to
 * `array<string, mixed>` one level down rather than repeating the shape.
 *
 * @phpstan-type Reference array{'$ref': string}
 * @phpstan-type ExternalDocumentation array{description?: string, url: string}
 * @phpstan-type ServerVariable array{enum?: non-empty-list<string>, default: string, description?: string}
 * @phpstan-type Server array{url: string, description?: string, variables?: array<string, ServerVariable>}
 * @phpstan-type Discriminator array{propertyName: string, mapping?: array<string, string>}
 * @phpstan-type Xml array{name?: string, namespace?: string, prefix?: string, attribute?: bool, wrapped?: bool}
 * @phpstan-type OpenApiSchema array{
 *     '$ref'?: string,
 *     title?: string,
 *     multipleOf?: float|int,
 *     maximum?: float|int,
 *     exclusiveMaximum?: bool,
 *     minimum?: float|int,
 *     exclusiveMinimum?: bool,
 *     maxLength?: int<0, max>,
 *     minLength?: int<0, max>,
 *     pattern?: string,
 *     maxItems?: int<0, max>,
 *     minItems?: int<0, max>,
 *     uniqueItems?: bool,
 *     maxProperties?: int<0, max>,
 *     minProperties?: int<0, max>,
 *     required?: non-empty-list<string>,
 *     enum?: non-empty-list<mixed>,
 *     type?: 'array'|'boolean'|'integer'|'number'|'object'|'string',
 *     allOf?: non-empty-list<array<string, mixed>>,
 *     oneOf?: non-empty-list<array<string, mixed>>,
 *     anyOf?: non-empty-list<array<string, mixed>>,
 *     not?: array<string, mixed>,
 *     items?: array<string, mixed>,
 *     properties?: array<string, array<string, mixed>>,
 *     additionalProperties?: bool|array<string, mixed>,
 *     description?: string,
 *     format?: string,
 *     default?: mixed,
 *     nullable?: bool,
 *     discriminator?: Discriminator,
 *     readOnly?: bool,
 *     writeOnly?: bool,
 *     xml?: Xml,
 *     externalDocs?: ExternalDocumentation,
 *     example?: mixed,
 *     deprecated?: bool,
 * }
 * @phpstan-type Example array{'$ref'?: string, summary?: string, description?: string, value?: mixed, externalValue?: string}
 * @phpstan-type Encoding array{contentType?: string, headers?: array<string, array<string, mixed>>, style?: string, explode?: bool, allowReserved?: bool}
 * @phpstan-type MediaType array{
 *     schema?: OpenApiSchema|Reference,
 *     example?: mixed,
 *     examples?: array<string, Example|Reference>,
 *     encoding?: array<string, Encoding>,
 * }
 * @phpstan-type Header array{
 *     '$ref'?: string,
 *     description?: string,
 *     required?: bool,
 *     deprecated?: bool,
 *     allowEmptyValue?: bool,
 *     style?: 'simple',
 *     explode?: bool,
 *     allowReserved?: bool,
 *     schema?: OpenApiSchema|Reference,
 *     example?: mixed,
 *     examples?: array<string, Example|Reference>,
 *     content?: array<string, MediaType>,
 * }
 * @phpstan-type Parameter array{
 *     '$ref'?: string,
 *     name?: string,
 *     in?: 'cookie'|'header'|'path'|'query',
 *     description?: string,
 *     required?: bool,
 *     deprecated?: bool,
 *     allowEmptyValue?: bool,
 *     style?: 'deepObject'|'form'|'label'|'matrix'|'pipeDelimited'|'simple'|'spaceDelimited',
 *     explode?: bool,
 *     allowReserved?: bool,
 *     schema?: OpenApiSchema|Reference,
 *     example?: mixed,
 *     examples?: array<string, Example|Reference>,
 *     content?: array<string, MediaType>,
 * }
 * @phpstan-type RequestBody array{'$ref'?: string, description?: string, content?: array<string, MediaType>, required?: bool}
 * @phpstan-type Link array{
 *     '$ref'?: string,
 *     operationRef?: string,
 *     operationId?: string,
 *     parameters?: array<string, mixed>,
 *     requestBody?: mixed,
 *     description?: string,
 *     server?: Server,
 * }
 * @phpstan-type Response array{
 *     '$ref'?: string,
 *     description?: string,
 *     headers?: array<string, Header|Reference>,
 *     content?: array<string, MediaType>,
 *     links?: array<string, Link|Reference>,
 * }
 * Response maps are keyed by status code. PHP coerces a numeric string key to
 * an int, so `'200' => [...]` arrives as `200 => [...]` and the key type has to
 * admit both — `default` and wildcards like `2XX` stay strings.
 * @phpstan-type SecurityRequirement array<string, list<string>>
 * @phpstan-type Operation array{
 *     tags?: list<string>,
 *     summary?: string,
 *     description?: string,
 *     externalDocs?: ExternalDocumentation,
 *     operationId?: string,
 *     parameters?: list<Parameter|Reference>,
 *     requestBody?: RequestBody|Reference,
 *     responses?: array<int|string, Response|Reference>,
 *     callbacks?: array<string, array<string, mixed>>,
 *     deprecated?: bool,
 *     security?: list<SecurityRequirement>,
 *     servers?: list<Server>,
 * }
 * @phpstan-type PathItem array{
 *     '$ref'?: string,
 *     summary?: string,
 *     description?: string,
 *     get?: Operation,
 *     put?: Operation,
 *     post?: Operation,
 *     delete?: Operation,
 *     options?: Operation,
 *     head?: Operation,
 *     patch?: Operation,
 *     trace?: Operation,
 *     servers?: list<Server>,
 *     parameters?: list<Parameter|Reference>,
 * }
 * @phpstan-type OAuthFlow array{authorizationUrl?: string, tokenUrl?: string, refreshUrl?: string, scopes: array<string, string>}
 * @phpstan-type OAuthFlows array{implicit?: OAuthFlow, password?: OAuthFlow, clientCredentials?: OAuthFlow, authorizationCode?: OAuthFlow}
 * @phpstan-type SecurityScheme array{
 *     '$ref'?: string,
 *     type?: 'apiKey'|'http'|'oauth2'|'openIdConnect',
 *     description?: string,
 *     name?: string,
 *     in?: 'cookie'|'header'|'query',
 *     scheme?: string,
 *     bearerFormat?: string,
 *     flows?: OAuthFlows,
 *     openIdConnectUrl?: string,
 * }
 * @phpstan-type Components array{
 *     schemas?: array<string, OpenApiSchema|Reference>,
 *     responses?: array<string, Response|Reference>,
 *     parameters?: array<string, Parameter|Reference>,
 *     examples?: array<string, Example|Reference>,
 *     requestBodies?: array<string, RequestBody|Reference>,
 *     headers?: array<string, Header|Reference>,
 *     securitySchemes?: array<string, SecurityScheme|Reference>,
 *     links?: array<string, Link|Reference>,
 *     callbacks?: array<string, array<string, mixed>>,
 * }
 */
#[Attribute(Attribute::TARGET_METHOD)]
class ApiSchema
{
    /**
     * Only `paths` and `components` are read from the fragment; the remaining
     * document-level fields come from the `openapi` config. Path keys are
     * resolved against `servers`, so declare the path the route serves minus
     * any base published in the server URL.
     *
     * @param  array{paths?: array<string, PathItem>, components?: Components}  $schema
     */
    public function __construct(public readonly array $schema = []) {}

    /**
     * Register the package's routes with no prefix or middleware of their own,
     * so the caller decides where they live.
     *
     * Used by the package's own route file, and available to applications that
     * set `openapi.route.enabled` to false and place the route themselves.
     */
    public static function routes(?string $uri = null, ?string $name = null): Registered
    {
        return Route::get(
            $uri ?? Config::string('openapi.route.uri', 'schema'),
            SchemaController::class,
        )->name($name ?? Config::string('openapi.route.name', 'openapi.schema'));
    }
}
