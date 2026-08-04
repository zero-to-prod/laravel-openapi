<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi\Internal\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Override;

/** @internal */
class Example extends Tool
{
    protected string $name = 'example';

    protected string $description = 'How to document and test an endpoint. Defaults to `start`: the attribute shape and the rules that cost a test cycle. A topic for one part, `all` for everything.';

    private const string HEADING = <<<'MARKDOWN'
        # Implementing and testing an endpoint

        Work through the steps in order. Steps 1-4 produce a documented endpoint;
        step 5 is what turns the documentation from a claim into a checked fact,
        and is the reason to use this package at all.

        Every snippet below is copied from this package's own test suite, so the
        controllers really do serve responses that validate, and the tests really
        do pass.
        MARKDOWN;

    /**
     * The default, in two halves. Enough to write a first correct endpoint
     * unaided, and no more: shape, then the four rules that otherwise cost a
     * test cycle, then where to go when one does. Diagnosis lives in `rules`
     * and `failures`, which an agent that has not run a test yet should not be
     * paying for.
     */
    private const string START_SHAPE = <<<'MARKDOWN'
        # Documenting an endpoint

        Declare the endpoint on the controller method with an attribute holding a
        plain OpenAPI 3.0.4 fragment. It is merged into the document verbatim, and
        nothing is inferred from your code.

        ```php
        use Illuminate\Http\JsonResponse;
        use ZeroToProd\LaravelOpenapi\ApiSchema;

        #[ApiSchema([
            'paths' => [
                '/articles/{id}' => [
                    'get' => [
                        'operationId' => 'showArticle',
                        'responses' => [
                            '200' => [
                                'description' => 'The article.',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'object',
                                            'required' => ['id'],
                                            'properties' => ['id' => ['type' => 'string']],
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
        ```
        MARKDOWN;

    private const string START_RULES = <<<'MARKDOWN'
        Four rules decide whether the tests pass:

        - **The response `Content-Type` has to match the declared media type.**
          `JsonResponse` sends `application/json`, so declare exactly that.
        - **Declare every status the method can return.** One you omit is not an
          undocumented extra; it is a failure the first time a test reaches it.
        - **The request is validated before the response.** A test for a 422 has to
          send a body the `requestBody` schema *accepts* and the application rejects.
        - **A declared `security` requirement must be satisfied by the test request.**
          `actingAs()` fakes the guard and sets no header, so add
          `->withToken('any-value')`, or the 401 can never be exercised either.

        Then test it. Wrap the response in `assertMatchesSchema()` — it resolves the
        operation from the request, so you never name the path, method or status —
        and write one test per declared status, which is what the coverage gate counts.

        ```php
        it('returns an article', function (): void {
            $this->assertMatchesSchema($this->getJson('articles/42')->assertOk());
        });
        ```
        MARKDOWN;

    /**
     * Keyed by topic, in document order. `all` concatenates them behind
     * HEADING; every other topic is served on its own.
     *
     * @var array<string, string>
     */
    private const array SECTIONS = [
        'setup' => <<<'MARKDOWN'
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
            MARKDOWN,

        'attribute' => <<<'MARKDOWN'
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
            MARKDOWN,

        'routing' => <<<'MARKDOWN'
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
            MARKDOWN,

        'testing' => <<<'MARKDOWN'
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
            MARKDOWN,

        'coverage' => <<<'MARKDOWN'
            ## 5. Gate it in CI

            Two checks, and they catch different things. Run both:

            ```bash
            php artisan openapi:validate
            php artisan openapi:coverage --reset && vendor/bin/pest && php artisan openapi:coverage
            ```

            - `openapi:validate` reads the merged document and reports **every**
              structural problem at once, dangling `$ref`s and security requirements
              naming a scheme nobody declared included. It exits `1` on failure. It
              says nothing about whether the document is *true*.
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
            MARKDOWN,

        'requestBody' => <<<'MARKDOWN'
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
            MARKDOWN,

        'security' => <<<'MARKDOWN'
            ## 7. Endpoints behind authentication

            `security` is checked **on the request**, the same way `requestBody` is,
            and before the response is looked at. That single fact accounts for both
            of the ways this goes wrong.

            ### Declare the scheme, then require it

            A `security` requirement names a scheme; it does not `$ref` one. The name
            has to resolve against `components.securitySchemes` or league rejects
            every request to the operation. Declare it in exactly one attribute —
            `components` merge across all of them — and require it per operation:

            ```php
            use Illuminate\Http\JsonResponse;
            use Illuminate\Http\Request;
            use ZeroToProd\LaravelOpenapi\ApiSchema;

            class SecureArticleController
            {
                #[ApiSchema([
                    'components' => [
                        'securitySchemes' => [
                            'bearer' => ['type' => 'http', 'scheme' => 'bearer'],
                        ],
                    ],
                    'paths' => [
                        '/secure-articles' => [
                            'get' => [
                                'operationId' => 'secureArticles',
                                'summary' => 'List articles for the authenticated caller',
                                'security' => [['bearer' => []]],
                                'responses' => [
                                    '200' => [
                                        'description' => 'The articles.',
                                        'content' => [
                                            'application/json' => [
                                                'schema' => [
                                                    'type' => 'object',
                                                    'required' => ['articles'],
                                                    'properties' => [
                                                        'articles' => ['type' => 'array', 'items' => ['type' => 'string']],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                    '401' => [
                                        'description' => 'The credential was missing or unrecognised.',
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
                    // Stands in for your own guard.
                    if ($request->bearerToken() !== 'valid-token') {
                        return new JsonResponse(['message' => 'Unauthenticated.'], 401);
                    }

                    return new JsonResponse(['articles' => ['Zero to prod']]);
                }
            }
            ```

            Forget the `components` half and `openapi:validate` will tell you, naming
            the operation. It did not always: a document requiring a scheme nobody
            declared used to pass the gate and fail every test instead.

            ### `actingAs()` does not satisfy a `security` requirement

            `actingAs()` and `Sanctum::actingAs()` fake the **guard**. They resolve the
            user directly and set no `Authorization` header. The request league sees
            therefore carries no credential, and it rejects it before your controller's
            response is ever examined:

            ```
            None of security schemas did match for Request [get /secure-articles]
            ```

            Add the header. The value is irrelevant when the guard is faked — it only
            has to be present and well-formed for an `http`/`bearer` scheme:

            ```php
            // Before — fails against any operation declaring `security`
            Sanctum::actingAs($user, ['some-ability']);
            $this->assertMatchesSchema($this->getJson('secure-articles'))->assertOk();

            // After — passes
            Sanctum::actingAs($user, ['some-ability']);
            $this->assertMatchesSchema(
                $this->withToken('any-value')->getJson('secure-articles')
            )->assertOk();
            ```

            ### This is also how a 401 becomes reachable

            The same header is what lets you cover the failure case. A request with a
            credential the document accepts but the application rejects passes request
            validation and reaches your 401:

            ```php
            it('rejects an unknown token', function (): void {
                $this->assertMatchesSchema(
                    $this->withToken('wrong-token')->getJson('secure-articles')
                )->assertUnauthorized();
            });
            ```

            Send no header and you get the request-validation failure above instead —
            so a declared 401 can *never* be exercised, and `openapi:coverage` fails
            for as long as the endpoint exists. Any operation declaring `security` and
            documenting a 401 needs this.

            `'security' => [['bearer' => []], []]` makes authentication optional and
            lets header-less requests validate, but it also documents the endpoint as
            public. Prefer `withToken()`.
            MARKDOWN,

        'rules' => <<<'MARKDOWN'
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
            - **The request is validated before the response.** A test for a 422 has
              to send a body the `requestBody` schema *accepts* and the application
              rejects, or the assertion fails describing the request instead.
            - **A declared `security` requirement must be satisfied by the test
              request.** `actingAs()` fakes the guard and sets no header, so add
              `->withToken('any-value')`. Without it the 401 can never be exercised
              either. Call `example` with `{"topic": "security"}` for the whole picture.
            - **The document is served unvalidated.** `GET /openapi.json` never fails
              because a fragment is incomplete — an endpoint that would tell you what
              is wrong should not be the one that breaks. `openapi:validate` is the
              check.
            - **Declared paths are not checked against the routes they annotate.** An
              attribute on a route at `/foo` may declare `/bar`, and
              `openapi:validate` passes: the document is well-formed, just untrue.
              Only the coverage gate catches it — requests to `/foo` resolve to no
              operation, and `/bar` is never exercised, so coverage fails.
            MARKDOWN,

        'failures' => <<<'MARKDOWN'
            ## What a failure is telling you

            | Message | Cause |
            |---------|-------|
            | `Body does not match schema for content-type "application/json" for Response [get /articles/{id} 200]`, followed by `caused by: Keyword validation failed: ...` | The response contradicts what you declared. The `caused by:` line names the property and the rule that broke. |
            | The same message naming a `Request` — `... for Request [post /articles]` | The test sent a body the `requestBody` schema rejects, so the response was never looked at. Fix the test, or widen the schema. |
            | `Mentioned security scheme 'x' not found in the given spec` | The operation declares `security` naming `x`, but no attribute declares `components.securitySchemes.x`. `openapi:validate` reports this too, naming the operation. |
            | `None of security schemas did match for Request [get /path]` | The test request carries no credential matching the declared scheme. `actingAs()` fakes the guard without setting a header — add `->withToken('any-value')`. |
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
            MARKDOWN,
    ];

    /** @return array<string, mixed> */
    #[Override]
    public function schema(JsonSchema $schema): array
    {
        return [
            'topic' => $schema->string()->description(
                'Defaults to `start`: the shape and the rules. Others: setup, '
                .'attribute, routing, testing, coverage, requestBody, security, rules, failures, all.'
            ),
        ];
    }

    public function handle(Request $request): Response
    {
        $topic = $request->get('topic');
        $topic = is_string($topic) && $topic !== '' ? $topic : 'start';

        if (isset(self::SECTIONS[$topic])) {
            return Response::text(self::SECTIONS[$topic]);
        }

        if ($topic === 'all') {
            return Response::text(self::content());
        }

        $start = implode("\n\n", [self::START_SHAPE, self::START_RULES, $this->index()]);

        return Response::text(
            $topic === 'start' ? $start : sprintf("There is no `%s` topic.\n\n%s", $topic, $start)
        );
    }

    public static function content(): string
    {
        return implode("\n\n", [self::HEADING, ...array_values(self::SECTIONS)]);
    }

    private function index(): string
    {
        return sprintf(
            "Topics: %s, all. This was `start`, the default.\n"
            .'`rules` and `failures` when a test fails; `all` for the complete worked example.',
            implode(', ', ['start', ...array_keys(self::SECTIONS)]),
        );
    }
}
