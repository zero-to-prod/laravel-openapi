# Laravel OpenAPI

Generate an OpenAPI 3.0 document from PHP attributes on your controllers, serve it over HTTP, and then verify it is
actually true — by validating real responses against it and failing your build when something you declared was never
tested.

A schema you hand-write is a claim. This package is built around the idea that a claim nobody checks is worth very
little, so it ships three layers:

1. **Generate** — `#[ApiSchema]` attributes on controller methods are merged into one document, served at `/openapi.json`.
2. **Validate the document** — `openapi:validate` checks it against the OpenAPI specification.
3. **Validate the behavior** — a test trait matches real requests and responses against the document, and
   `openapi:coverage` fails when a declared response was never exercised.

## Requirements

- PHP `^8.3`
- Laravel 11, 12, or 13

## Installation

```bash
composer require zero-to-prod/laravel-openapi
```

The service provider is auto-discovered. Publish the config if you want to change anything:

```bash
php artisan vendor:publish --tag=openapi-config
```

Two optional dev dependencies unlock layers 2 and 3. Neither is needed to generate or serve the document:

```bash
# for `openapi:validate`
composer require --dev devizzent/cebe-php-openapi

# for the ValidatesSchema test trait
composer require --dev league/openapi-psr7-validator symfony/psr-http-message-bridge nyholm/psr7
```

## Quick start

Annotate a controller method with the OpenAPI fragment that describes it. The array is plain OpenAPI — whatever you
write here is what ends up in the document, so anything the specification allows is available to you:

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

`GET /openapi.json` now serves:

```json
{
  "openapi": "3.0.4",
  "info": { "title": "JSON:API", "version": "1.0.0" },
  "servers": [{ "url": "/" }],
  "paths": {
    "/articles/{id}": { "get": { "operationId": "showArticle", "...": "..." } },
    "/openapi.json": { "get": { "operationId": "getSchema", "...": "..." } }
  }
}
```

### Declared paths are relative to `servers`

OpenAPI resolves every path against the first server URL, which defaults to `/`. So declare the path the route
actually serves: a route at `/articles/{id}` is declared as `/articles/{id}`.

If your whole API sits under a common base, set it once in `servers` and drop it from every attribute — a route at
`/api/articles/{id}` with `servers` of `/api` is declared as `/articles/{id}`. The response validator honours the
server URL either way, so both styles are checked against real traffic.

## How the document is assembled

`SchemaGenerator` walks every registered route, reflects the controller method behind it, and merges the `paths` and
`components` from each `#[ApiSchema]` attribute it finds. Routes without the attribute are ignored, so this package
never invents documentation for endpoints you did not describe.

Document-level fields that cannot be derived from routes come from config:

```php
'openapi' => [
    'openapi' => '3.0.4',
    'info' => ['title' => 'JSON:API', 'version' => '1.0.0'],
    'servers' => [['url' => '/']],
],
```

The merged document is served verbatim. It is deliberately *not* validated on the way out — an incomplete fragment
should not break the endpoint that would tell you about it. That job belongs to `openapi:validate`.

## Routing

By default the package registers one route: `GET /openapi.json`, named `openapi.schema`. Every part is configurable:

| Key                        | Default          | Purpose                                       |
|----------------------------|------------------|-----------------------------------------------|
| `openapi.route.enabled`    | `true`           | Register the route at all                     |
| `openapi.route.uri`        | `'openapi.json'` | URI within the prefix                         |
| `openapi.route.name`       | `openapi.schema` | Route name                                    |
| `openapi.route.prefix`     | `''`             | Group prefix                                  |
| `openapi.route.middleware` | `['api']`        | Group middleware                              |

For anything config cannot express — auth, domains, throttling, nested groups — turn the default off and place the
route yourself:

```php
// config/openapi.php
'route' => ['enabled' => false, /* ... */],

// routes/api.php
use ZeroToProd\LaravelOpenapi\ApiSchema;

Route::middleware('auth:sanctum')
    ->prefix('internal')
    ->group(fn () => ApiSchema::routes());
```

`ApiSchema::routes()` registers the route with no prefix or middleware of its own and returns the `Route`, so you can
keep configuring it. It also accepts an explicit URI and name:

```php
ApiSchema::routes('docs/openapi.json', 'docs.schema')->middleware('throttle:60,1');
```

## Validating the document

```bash
php artisan openapi:validate
```

```
INFO  The generated document is a valid OpenAPI 3.0.4 document (2 paths).
```

On failure it reports **every** problem at once and exits `1`:

```
ERROR  The generated document is not a valid OpenAPI 3.0.4 document.

  ⇂ Failed to resolve Reference '#/components/schemas/DoesNotExist' ...
  ⇂ Operation is missing required property: responses
```

Dangling `$ref`s are included: references are resolved as well as validated, because a document can be structurally
valid while pointing at components that do not exist.

## Validating behavior against the document

This is the layer that turns the schema from a claim into a checked fact. Add the trait to your base `TestCase`:

```php
use ZeroToProd\LaravelOpenapi\Internal\ValidatesSchema;

abstract class TestCase extends BaseTestCase
{
    use ValidatesSchema;
}
```

Then assert against it:

```php
$this->assertMatchesSchema($this->getJson('articles/42'));
```

The operation is resolved from the request automatically — you never name the path or method. Both the request and the
response are checked. Failures name the operation and the keyword that broke:

```
Body does not match schema for content-type "application/vnd.api+json" for Response [get /articles/{id} 200]
  caused by: Keyword validation failed: Required property 'title' must be present in the object
```

Undeclared status codes are caught too, which is the mirror image of the usual problem — not "the schema lies about
the body" but "the endpoint does something the schema never mentions":

```
OpenAPI spec contains no such operation [/undeclared-status,get,418]
```

## Coverage: catching what you never tested

Response validation only proves the endpoints your tests reach are honest. An operation nobody exercised is exactly
as unverified as it was before you added the assertion. `assertMatchesSchema()` records every
`(path, method, status)` it validates, appended as JSON Lines so separate processes and parallel workers can share
one file.

```bash
php artisan openapi:coverage --reset && vendor/bin/pest && php artisan openapi:coverage
```

```
ERROR  1 of 2 declared responses were never exercised.

  ⇂ GET /articles/{id} -> 404
```

Exits `1` when anything is missing, so it works as a CI gate. Granularity is per response, not per operation: a `422`
you declare but never exercise is precisely the unverified claim this catches. `2XX` ranges and `default` count as
covered by any matching concrete status.

`assertSchemaFullyExercised()` is also available for the same check inside a single-process suite.

## Configuration reference

| Key                     | Default                                                | Purpose                                |
|-------------------------|--------------------------------------------------------|----------------------------------------|
| `openapi.route.*`       | see above                                              | Where the schema endpoint lives        |
| `openapi.openapi`       | `3.0.4` / `JSON:API` / `1.0.0` / `[{url: '/'}]`        | Document-level fields                  |
| `openapi.coverage.path` | `storage/framework/cache/openapi-coverage.jsonl`       | Where validated responses are recorded |

## Known limitations

Worth knowing before you rely on any of this.

**Declared paths are not checked against the routes they annotate.** An attribute on a route at `/foo` can declare
`/bar`, and `openapi:validate` will happily pass — the document is well-formed, just untrue. The behavior layer is
what catches it: requests to `/foo` resolve to no operation, and `/bar` is never exercised so coverage fails. If you
only run `openapi:validate`, this class of drift is invisible.

**Changing `openapi.route.uri` desyncs the built-in endpoint's own documentation.** `SchemaController` declares itself
at `/openapi.json` in a PHP attribute, which cannot read config. Move the route and the document still describes the old
path. This affects only this package's endpoint, and `assertMatchesSchema()` reports it as `no such operation`.

**Response body validation is fail-fast.** A response violating three rules reports one. You fix them one round-trip
at a time. Document validation, by contrast, reports everything at once.

**Coverage accumulates in static state plus the file.** `--reset` deletes the shared file, so run it before a suite,
never between tests. `assertSchemaFullyExercised()` relies on in-process state and must run after the rest of the
suite; under `pest --parallel`, use the `openapi:coverage` command instead.

**`openapi:coverage` sees the routes registered in its own process.** Routes that exist only inside a test cannot be
reported as missing.

## Contributing

```bash
composer install
composer test
```

The suite uses [Pest](https://pestphp.com) and [Testbench](https://packages.tools/testbench). `TestCase::withConfig()`
rebuilds the application so the provider boots against new config — necessary because route registration happens
during boot.

## License

MIT
