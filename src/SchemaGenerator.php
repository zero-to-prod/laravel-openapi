<?php

declare(strict_types=1);

namespace ZeroToProd\JsonApi;

use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use ReflectionMethod;
use Zerotoprod\DataModelOpenapi30\OpenApi;

/**
 * Builds an OpenAPI document from the #[JsonApi] attributes declared on the
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
     * The hydrated document. Throws if the merged fragments are not a valid
     * OpenAPI document.
     */
    public function generate(): OpenApi
    {
        return OpenApi::from($this->document());
    }

    /**
     * The merged document, before hydration.
     *
     * @return array<string, mixed>
     */
    public function document(): array
    {
        $paths = [];
        $components = [];

        foreach ($this->router->getRoutes() as $route) {
            $schema = $this->schemaFor($route);

            $paths[] = $schema[OpenApi::paths] ?? [];
            $components[] = $schema[OpenApi::components] ?? [];
        }

        $paths = array_replace_recursive([], ...$paths);
        $components = array_replace_recursive([], ...$components);

        ksort($paths);

        return array_replace_recursive(
            $this->document,
            [OpenApi::paths => $paths],
            $components === [] ? [] : [OpenApi::components => $components],
        );
    }

    /**
     * The #[JsonApi] fragment declared on the method handling the given route.
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

        $attribute = (new ReflectionMethod($controller, $method))->getAttributes(JsonApi::class)[0] ?? null;

        return $attribute?->newInstance()->schema ?? [];
    }
}
