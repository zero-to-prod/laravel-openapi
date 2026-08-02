# Laravel OpenAPI

Laravel OpenAPI generates an [OpenAPI](https://www.openapis.org/) document to help users build confidently with your API.

The package uses the [OpenAPI specification](https://spec.openapis.org/oas/v3.0.4.html) as part of the package API itself.

This is especially useful for AI agents because many models are trained to understand the OpenAPI specification.

They can build with this package effectively without specialized knowledge or skills.

## Requirements

- PHP `^8.3`
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

### Agent Development

This project ships with an [MCP server](#mcp-server) to aid in agent development.

## Quick start

Annotate a controller method.

Whatever you write here is placed in the document.

Use the [validation](#validation) tools to prove the schema conforms to the [OpenAPI specification](https://spec.openapis.org/oas/v3.0.4.html).

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

The merged document is served verbatim. Use `openapi:validate` to validate before deployment.

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

Override the the route:

```php
ApiSchema::routes('docs/openapi.json', 'docs.schema')->middleware('throttle:60,1');
```

## Validation

This package ships with optional validation tools to verify the schema and behavior

```bash
composer require --dev devizzent/cebe-php-openapi
composer require --dev league/openapi-psr7-validator symfony/psr-http-message-bridge nyholm/psr7
```

### Schema Validation

This command builds the document and validates it against the OpenAPI specification.

If it fails the command will return any errors, otherwise it returns a `0` making it useful for build pipelines.

```bash
php artisan openapi:validate
```

### Behavior Validation

If you want to prove the schema matches your applications behavior, this package comes with built-in assertions that you can use in your tests.

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

The operation is resolved from the request automatically.

Both the request and the response are checked.

## Coverage

Make sure every defined path method and status are covered by your tests.

Use `assertMatchesSchema()` to record every`(path, method, status)` for validation.

Example:

```bash
php artisan openapi:coverage --reset && vendor/bin/pest && php artisan openapi:coverage
```

```
ERROR  1 of 2 declared responses were never exercised.

  ⇂ GET /articles/{id} -> 404
```

## MCP server

Install the dependencies via composer.

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

```text
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
