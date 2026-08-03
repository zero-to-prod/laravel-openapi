<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi;

use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use ReflectionAttribute;
use ReflectionMethod;

/**
 * @phpstan-import-type PathItem from ApiSchema
 * @phpstan-import-type Components from ApiSchema
 */
readonly class SchemaGenerator
{
    /**
     * @param  Router  $router  The application router; supply app(Router::class).
     * @param  array<string, mixed>  $document  Document-level OpenAPI fields merged into every response: openapi, info, servers, security, tags, externalDocs.
     * @param  int  $attributeFlags  Passed to ReflectionMethod::getAttributes(). Default IS_INSTANCEOF matches ApiSchema and any subclass; pass 0 for exact-class-only matching.
     */
    public function __construct(
        private Router $router,
        private array $document = [],
        private int $attributeFlags = ReflectionAttribute::IS_INSTANCEOF,
    ) {}

    /** @return array<string, mixed> */
    public function document(): array
    {
        $paths = [];
        $components = [];

        foreach ($this->inventory() as $entry) {
            $paths[] = $entry['schema']['paths'] ?? [];
            $components[] = $entry['schema']['components'] ?? [];
        }

        $paths = array_replace_recursive([], ...$paths);
        $components = array_replace_recursive([], ...$components);

        ksort($paths);

        return array_replace_recursive(
            $this->document,
            ['paths' => $paths],
            $components === [] ? [] : ['components' => $components],
        );
    }

    /**
     * Every registered route paired with the schema its handler declares. The
     * document is one reduction of this; the MCP `status` tool is another, and
     * needs the routes that declared nothing, which the document cannot show.
     *
     * @internal
     *
     * @return list<array{uri: string, methods: list<string>, action: string|null, documented: bool, schema: array{paths?: array<string, PathItem>, components?: Components}}>
     */
    public function inventory(): array
    {
        $inventory = [];

        foreach ($this->router->getRoutes()->getRoutes() as $route) {
            $method = $this->methodFor($route);
            $attribute = $method?->getAttributes(ApiSchema::class, $this->attributeFlags)[0] ?? null;

            $inventory[] = [
                'uri' => '/'.ltrim($route->uri(), '/'),
                'methods' => array_values(array_diff($route->methods(), ['HEAD'])),
                'action' => $method instanceof ReflectionMethod ? $method->getDeclaringClass()->getName().'::'.$method->getName() : null,
                'documented' => $attribute !== null,
                'schema' => $attribute?->newInstance()->schema ?? [],
            ];
        }

        return $inventory;
    }

    /** The controller method behind a route, or null when a closure handles it. */
    private function methodFor(Route $route): ?ReflectionMethod
    {
        $controller = $route->getControllerClass();

        if ($controller === null) {
            return null;
        }

        $method = $route->getActionMethod();
        $method = $method === $controller ? '__invoke' : $method;

        if (! method_exists($controller, $method)) {
            return null;
        }

        return new ReflectionMethod($controller, $method);
    }
}
