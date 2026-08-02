<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi\Internal\Mcp\Tools;

use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/** @internal */
class Example extends Tool
{
    protected string $name = 'example';

    protected string $description = 'A complete worked example of documenting and testing one endpoint: the #[ApiSchema] attribute, the route, the tests, the CI gate, and the failure messages.';

    private const string EXAMPLE = <<<'MARKDOWN'
        # Implementing and testing an endpoint

        Work through the steps in order. Steps 1-4 produce a documented endpoint;
        step 5 is what turns the documentation from a claim into a checked fact,
        and is the reason to use this package at all.

        Every snippet below is copied from this package's own test suite, so the
        controllers really do serve responses that validate, and the tests really
        do pass.

        ## 1. Set up the application once

        Generating and serving the document needs nothing beyond the package. The
        test-side assertions need three more dev dependencies, and the document
        validator a fourth:

        ```bash
        composer require --dev league/openapi-psr7-validator symfony/psr-http-message-bridge nyholm/psr7
        composer require --dev devizzent/cebe-php-openapi
        ```

        Add the trait to the base test case, so every test can assert against the
        document:

        ```php
        use ZeroToProd\LaravelOpenapi\ValidatesSchema;

        abstract class TestCase extends BaseTestCase
        {
            use ValidatesSchema;
        }
        ```

        ## 2. Declare the endpoint on the controller method

        The attribute holds a plain OpenAPI fragment. It is merged into the
        document verbatim, so anything the OpenAPI 3.0.4 specification allows is
        available, and nothing is inferred from your code.

        Declare **every** status the method can return. A status you omit is not
        an undocumented extra; it is a test failure the first time a test reaches
        it.

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
                            'summary' => 'Get one article',
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
                                        'application/json' => [
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
                                '404' => [
                                    'description' => 'No article has that id.',
                                    'content' => [
                                        'application/json' => [
                                            'schema' => [
                                                'type' => 'object',
                                                'required' => ['message'],
                                                'properties' => [
                                                    'message' => ['type' => 'string'],
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
                // Stands in for your own lookup.
                $title = $id === '42' ? 'Zero to prod' : null;

                if ($title === null) {
                    return new JsonResponse(['message' => sprintf('No article has the id %s.', $id)], 404);
                }

                return new JsonResponse(['id' => $id, 'title' => $title]);
            }
        }
        ```

        ## 3. Register the route

        Nothing about routing changes. Register the route however you already do:

        ```php
        use Illuminate\Support\Facades\Route;

        Route::get('articles/{id}', ShowArticleController::class);
        ```

        The path key in the attribute and the URI the route is registered at have
        to describe the same endpoint, because the two are matched at test time by
        the path, not by the attribute's location. Two things decide whether they
        line up:

        - **Placeholders use the OpenAPI name, and it must match the route
          parameter.** A route at `articles/{id}` is declared as
          `/articles/{id}` — same word inside the braces.
        - **Declared paths resolve against the first `servers` URL**, which
          defaults to `/`. With that default, declare the full path the route
          serves. If the whole API lives under a common base, set it once in
          `openapi.servers` and leave it out of every attribute: with a server URL
          of `/api`, a route at `api/articles/{id}` is declared as
          `/articles/{id}`.

        ## 4. Test every response you declared

        `assertMatchesSchema()` resolves the operation from the request, so you
        never name the path, method or status. It checks the request and the
        response, returns the `TestResponse` for chaining, and records the
        `(path, method, status)` it validated for the coverage check in step 5.

        One test per declared response — that is the granularity coverage is
        measured at:

        ```php
        it('returns an article', function (): void {
            $this->assertMatchesSchema($this->getJson('articles/42')->assertOk());
        });

        it('reports a missing article', function (): void {
            $this->assertMatchesSchema($this->getJson('articles/99')->assertNotFound());
        });
        ```

        Keep the ordinary assertions. `assertMatchesSchema()` proves the response
        is *shaped* the way the document promises; it does not prove the values
        are right, so assert on those as you normally would:

        ```php
        $this->assertMatchesSchema($this->getJson('articles/42'))
            ->assertOk()
            ->assertJsonPath('title', 'Zero to prod');
        ```

        ## 5. Gate it in CI

        Two checks, and they catch different things. Run both:

        ```bash
        php artisan openapi:validate
        php artisan openapi:coverage --reset && vendor/bin/pest && php artisan openapi:coverage
        ```

        - `openapi:validate` reads the merged document and reports **every**
          structural problem at once, dangling `$ref`s included. It exits `1` on
          failure. It says nothing about whether the document is *true*.
        - `openapi:coverage` fails when a declared response was never exercised by
          `assertMatchesSchema()`. `--reset` discards the previous run's record,
          so it belongs before the suite, never between tests.

        ```
        ERROR  1 of 3 declared responses were never exercised.

          ⇂ GET /articles/{id} -> 404
        ```

        `assertSchemaFullyExercised()` makes the same assertion from inside a
        single-process suite, and has to run after everything else. Under
        `pest --parallel` it cannot see the other workers, so use the command.

        ## 6. Endpoints that accept a body

        The request is validated too, against `requestBody`. The example below
        declares `title` as a required string, and rejects a blank one with a 422
        of its own:

        ```php
        use Illuminate\Http\JsonResponse;
        use Illuminate\Http\Request;
        use ZeroToProd\LaravelOpenapi\ApiSchema;

        class StoreArticleController
        {
            #[ApiSchema([
                'paths' => [
                    '/articles' => [
                        'post' => [
                            'operationId' => 'storeArticle',
                            'summary' => 'Create an article',
                            'requestBody' => [
                                'required' => true,
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'object',
                                            'required' => ['title'],
                                            'properties' => [
                                                'title' => ['type' => 'string'],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            'responses' => [
                                '201' => [
                                    'description' => 'The created article.',
                                    'content' => [
                                        'application/json' => [
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
                                '422' => [
                                    'description' => 'The title was blank.',
                                    'content' => [
                                        'application/json' => [
                                            'schema' => [
                                                'type' => 'object',
                                                'required' => ['message'],
                                                'properties' => [
                                                    'message' => ['type' => 'string'],
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
            public function __invoke(Request $request): JsonResponse
            {
                $title = trim($request->string('title')->toString());

                if ($title === '') {
                    return new JsonResponse(['message' => 'The title field is required.'], 422);
                }

                // Stands in for your own persistence.
                return new JsonResponse(['id' => '42', 'title' => $title], 201);
            }
        }
        ```

        ```php
        it('creates an article', function (): void {
            $this->assertMatchesSchema($this->postJson('articles', ['title' => 'Zero to prod']));
        });

        it('rejects a blank title', function (): void {
            $this->assertMatchesSchema($this->postJson('articles', ['title' => '  ']));
        });
        ```

        Note what the second test sends. A request body that violates the declared
        `requestBody` schema fails the assertion **on the request**, before the
        response is looked at — so a test for a 422 has to send a body the schema
        accepts and the application rejects. `['title' => '  ']` is a valid string
        and a blank title; `[]` would be a missing required property, and the
        assertion would fail describing the request rather than the 422 you meant
        to cover.

        That leaves a real gap: input the schema itself forbids can never reach
        your 422 branch through `assertMatchesSchema()`. Cover those cases with an
        ordinary test that skips the assertion, or widen the request schema and
        let the application do the rejecting.

        ## Rules that decide whether this works

        - **One attribute per controller method.** Routes without one are skipped
          entirely — this package never invents documentation you did not write.
        - **The response `Content-Type` has to match the declared media type.**
          `JsonResponse` sends `application/json`, which is why the examples above
          declare exactly that. Declaring `application/vnd.api+json` means setting
          the header to match: `new JsonResponse($data, headers: ['Content-Type' =>
          'application/vnd.api+json'])`.
        - **Status keys are quoted strings** — `'200'`, not `200`. PHP coerces the
          key to an int either way, and both are handled, but the quoted form is
          what OpenAPI documents look like. `'2XX'` ranges and `'default'` are
          allowed, and count as covered by any matching concrete status.
        - **`components` merge across attributes.** Declare a shared schema under
          `'components' => ['schemas' => [...]]` in one attribute and reference it
          from any other with `['$ref' => '#/components/schemas/Article']`.
          `openapi:validate` resolves references, so a `$ref` pointing at nothing
          fails there rather than silently serving a broken document.
        - **The document is served unvalidated.** `GET /openapi.json` never fails
          because a fragment is incomplete — an endpoint that would tell you what
          is wrong should not be the one that breaks. `openapi:validate` is the
          check.
        - **Declared paths are not checked against the routes they annotate.** An
          attribute on a route at `/foo` may declare `/bar`, and
          `openapi:validate` passes: the document is well-formed, just untrue.
          Only step 5 catches it — requests to `/foo` resolve to no operation, and
          `/bar` is never exercised, so coverage fails.

        ## What a failure is telling you

        | Message | Cause |
        |---------|-------|
        | `Body does not match schema for content-type "application/json" for Response [get /articles/{id} 200]`, followed by `caused by: Keyword validation failed: ...` | The response contradicts what you declared. The `caused by:` line names the property and the rule that broke. |
        | The same message naming a `Request` — `... for Request [post /articles]` | The test sent a body the `requestBody` schema rejects, so the response was never looked at. Fix the test, or widen the schema. |
        | `OpenAPI spec contains no such operation [/articles/42,get]` — **two** elements | No declared path matches the request at all. Either nothing declares that path, or a placeholder is spelled differently from the route parameter. |
        | `OpenAPI spec contains no such operation [/undeclared-status,get,418]` — **three** elements | The path and method are declared; that status is not. Add it to `responses`. |
        | `The response was not produced by an HTTP test request, so no operation can be resolved.` | The `TestResponse` was constructed directly. It has to come from `getJson()`, `postJson()` and friends, which is where the request comes from. |
        | `Matching responses against the schema requires league/openapi-psr7-validator.` | Step 1's dev dependencies are missing. |
        | `The document declares N response(s) that no test exercised:` | `assertSchemaFullyExercised()` found declared responses no test reached. Add a test per listed entry. |

        The path in a `no such operation` message is the concrete path the test
        requested, not the template it failed to match — `/articles/42`, not
        `/articles/{id}`. Search the document for the declaration, not for the
        string in the message.

        Response body validation is fail-fast: a response breaking three rules
        reports one, so expect to fix them one run at a time. Document validation
        is the opposite and reports everything at once.
        MARKDOWN;

    public function handle(): Response
    {
        return Response::text(self::EXAMPLE);
    }

    public static function content(): string
    {
        return self::EXAMPLE;
    }
}
