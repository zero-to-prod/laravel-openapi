<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi;

use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use ReflectionMethod;

/**
 * @phpstan-import-type PathItem from ApiSchema
 * @phpstan-import-type Components from ApiSchema
 */
readonly class SchemaGenerator
{
    /** @param  array<string, mixed>  $document  Document-level fields: openapi, info, servers, ... */
    public function __construct(private Router $router, private array $document = []) {}

    /** @return array<string, mixed> */
    public function document(): array
    {
        $paths = [];
        $components = [];

        foreach ($this->router->getRoutes()->getRoutes() as $route) {
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

    /** @return array{paths?: array<string, PathItem>, components?: Components} */
    private function schemaFor(Route $route): array
    {
        $controller = $route->getControllerClass();

        if ($controller === null) {
            return [];
        }

        $method = $route->getActionMethod();
        $method = $method === $controller ? '__invoke' : $method;

        if (! method_exists($controller, $method)) {
            return [];
        }

        $attribute = (new ReflectionMethod($controller, $method))->getAttributes(ApiSchema::class)[0] ?? null;

        return $attribute?->newInstance()->schema ?? [];
    }
}
