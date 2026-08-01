<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi;

use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use ReflectionMethod;

/**
 * Builds an OpenAPI document from the #[ApiSchema] attributes declared on the
 * controller methods behind the application's registered routes.
 */
class SchemaGenerator
{
    /**
     * @param  array<string, mixed>  $document  Document-level fields: openapi, info, servers, ...
     */
    public function __construct(
        private readonly Router $router,
        private readonly array $document = [],
    ) {
    }

    /**
     * The merged document. Validity is asserted by `openapi:validate` rather
     * than on the way out, so an incomplete fragment does not break the
     * endpoint that would tell you about it.
     *
     * @return array<string, mixed>
     */
    public function document(): array
    {
        $paths = [];
        $components = [];

        foreach ($this->router->getRoutes() as $route) {
            $schema = $this->schemaFor($route);

            $paths[] = $schema['paths'] ?? [];
            $components[] = $schema['components'] ?? [];
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
     * The #[ApiSchema] fragment declared on the method handling the given route.
     *
     * @return array<string, mixed>
     */
    public function schemaFor(Route $route): array
    {
        $controller = $route->getControllerClass();

        if ($controller === null) {
            return [];
        }

        // Invokable controllers are registered without an `@method` suffix, so
        // Laravel reports the class itself as the action method.
        $method = $route->getActionMethod();
        $method = $method === $controller ? '__invoke' : $method;

        if (!method_exists($controller, $method)) {
            return [];
        }

        $attribute = (new ReflectionMethod($controller, $method))->getAttributes(ApiSchema::class)[0] ?? null;

        return $attribute?->newInstance()->schema ?? [];
    }
}
