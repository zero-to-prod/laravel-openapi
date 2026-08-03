# Laravel OpenAPI

Laravel OpenAPI generates an [OpenAPI](https://www.openapis.org/) document to help users build confidently with your API.

The package uses the [OpenAPI specification](https://spec.openapis.org/oas/v3.0.4.html) as part of the package API itself.

This is especially useful for AI agents, because many models are already trained on the OpenAPI specification. They can build with this
package effectively without specialized knowledge or skills.

## Requirements

- PHP `^8.4`
- [Laravel](https://laravel.com/) 12 or 13

## Installation

Laravel OpenAPI can be installed via Composer:

```bash
composer require zero-to-prod/laravel-openapi
```

### Configuration

You may want to publish the configuration file to override or customize the behavior.

```bash
php artisan vendor:publish --tag=openapi-config
```

### Agent development

This project ships with an [MCP server](#mcp-server) to aid in agent development.

## Quick start

Annotate a controller method. Whatever you write here is placed in the document.

Use the [validation](#validation) tools to prove the document conforms to the [OpenAPI specification](https://spec.openapis.org/oas/v3.0.4.html)
and that your application actually behaves the way it claims.

```php
use Illuminate\Http\JsonResponse;
use ZeroToProd\LaravelOpenapi\ApiSchema;

class ShowArticleController
{
    #[ApiSchema([
        'paths' => [
            '/articles/{id}' => [
                'get' => [
                    'operationId' => 'showArticle',
                    'parameters' => [
                        [
                            'name' => 'id',
                            'in' => 'path',
                            'required' => true,
                            'schema' => ['type' => 'string'],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'The article.',
                            'content' => [
                                'application/vnd.api+json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['id', 'title'],
                                        'properties' => [
                                            'id' => ['type' => 'string'],
                                            'title' => ['type' => 'string'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ])]
    public function __invoke(string $id): JsonResponse
    {
        return new JsonResponse(
            data: ['id' => $id, 'title' => 'Zero to prod'],
            headers: ['Content-Type' => 'application/vnd.api+json'],
        );
    }
}
```

`GET /openapi.json` serves:

```json
{
    "openapi": "3.0.4",
    "info": {
        "title": "Laravel",
        "version": "1.0.0"
    },
    "servers": [
        {
            "url": "/"
        }
    ],
    "paths": {
        "/articles/{id}": {
            "get": {
                "operationId": "showArticle",
                "...": "..."
            }
        },
        "/openapi.json": {
            "get": {
                "operationId": "getSchema",
                "...": "..."
            }
        }
    }
}
```

The fragment on every registered route is merged into the document-level fields from `config/openapi.php`, paths are sorted, and the
result is served verbatim. Nothing is rewritten or checked on the way out, so run [`openapi:validate`](#schema-validation) before you
deploy.

Paths in the attribute are resolved relative to the first entry in `openapi.servers`. With the default of `/` they are absolute, so
declare the path the route actually serves.

## Routing

By default, the package registers one route: `GET /openapi.json`, named `openapi.schema`.

| Key                        | Default          | Purpose                   |
|----------------------------|------------------|---------------------------|
| `openapi.route.enabled`    | `true`           | Register the route at all |
| `openapi.route.uri`        | `'openapi.json'` | URI within the prefix     |
| `openapi.route.name`       | `openapi.schema` | Route name                |
| `openapi.route.prefix`     | `''`             | Group prefix              |
| `openapi.route.middleware` | `['api']`        | Group middleware          |

For full customization, turn the default off and place the route:

```php
// config/openapi.php
'route' => ['enabled' => false, /* ... */],

// routes/api.php
use ZeroToProd\LaravelOpenapi\ApiSchema;

Route::middleware('auth:sanctum')
    ->prefix('internal')
    ->group(fn () => ApiSchema::routes());
```

Override the route:

```php
ApiSchema::routes('docs/openapi.json', 'docs.schema')->middleware('throttle:60,1');
```

Moving the endpoint does not move how it is documented: the document always describes this route as `/openapi.json`. Keep the two in
step, or accept that the document describes the old path.

## Validation

This package ships with optional validation tools to verify both the document and the behavior behind it.

### Schema validation

This command builds the document and validates it against the OpenAPI specification.

```bash
composer require --dev devizzent/cebe-php-openapi
```

```bash
php artisan openapi:validate
```

On failure it lists the specification errors and exits non-zero; on success it exits `0`, which makes it useful in a build pipeline.

### Behavior validation

If you want to prove the document matches your application's behavior, this package comes with built-in assertions that you can use in
your tests.

```bash
composer require --dev league/openapi-psr7-validator symfony/psr-http-message-bridge nyholm/psr7
```

```php
use ZeroToProd\LaravelOpenapi\ValidatesSchema;

abstract class TestCase extends BaseTestCase
{
    use ValidatesSchema;
}
```

Then assert against it:

```php
$this->assertMatchesSchema($this->getJson('articles/42'));
```

The operation is resolved from the request automatically, and both the request and the response are checked. The response is returned,
so the assertion can be chained onto an existing call.

## Coverage

A declared response that no test ever exercises is a claim nothing checks. Every call to `assertMatchesSchema()` records the
`(path, method, status)` it validated, so you can find the ones you missed.

Reset the record before the suite runs, then report on it afterwards:

```bash
php artisan openapi:coverage --reset && vendor/bin/pest && php artisan openapi:coverage
```

```
ERROR  1 of 2 declared responses were never exercised.

  ⇂ GET /articles/{id} -> 404
```

The command exits non-zero while anything is missing, so it gates a build the same way `openapi:validate` does.

Records are appended to `storage/framework/cache/openapi-coverage.jsonl`, which is append-only JSON Lines so parallel test workers can
share it. Change the location with `openapi.coverage.path`.

To assert the same thing from inside the suite, call `assertSchemaFullyExercised()` from a test that runs last. It only sees what the
current process recorded, so prefer the command when your suite runs in parallel.

## MCP server

The package registers an MCP server so coding agents can read how it is meant to be used.

Install the dependency via composer.

```bash
composer require --dev laravel/mcp
```

### Set up your agents

```bash tab=Claude Code
claude mcp add -s local -t stdio laravel-openapi php artisan mcp:start laravel-openapi
```

```bash tab=Codex
codex mcp add laravel-openapi -- php "artisan" "mcp:start" "laravel-openapi"
```

```bash tab=Gemini CLI
gemini mcp add -s project -t stdio laravel-openapi php artisan mcp:start laravel-openapi
```

For an agent configured by file, add the server directly:

```json
{
    "mcpServers": {
        "laravel-openapi": {
            "type": "stdio",
            "command": "php",
            "args": [
                "artisan",
                "mcp:start",
                "laravel-openapi"
            ]
        }
    }
}
```
