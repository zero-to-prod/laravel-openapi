<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi\Internal;

use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;
use ZeroToProd\LaravelOpenapi\ApiSchema;

/**
 * The attribute class an application actually annotates its controllers with.
 *
 * `SchemaGenerator` matches attributes with `ReflectionAttribute::IS_INSTANCEOF`
 * precisely so an application can declare its own subclass, and applications
 * do. An agent shown the generic `#[ApiSchema([...])]` shape in such a project
 * writes a second, competing convention — so both MCP tools name what is
 * already there instead.
 *
 * @internal
 *
 * @phpstan-type Counted array{action: string|null, documented: bool, attribute?: string|null}
 */
final readonly class LocalConvention
{
    /**
     * @param  string  $class  The attribute class, as reported by the inventory.
     * @param  int  $count  Documented routes in scope carrying it.
     * @param  string  $action  One of them, as a call site worth reading.
     */
    private function __construct(
        public string $class,
        public int $count,
        public string $action,
    ) {}

    /**
     * Every attribute class in use, most-used first. Ties keep route order, so
     * the same application always reports the same dominant class.
     *
     * @param  list<Counted>  $entries
     * @return list<self>
     */
    public static function all(array $entries): array
    {
        $counts = [];
        $actions = [];

        foreach ($entries as $entry) {
            $class = $entry['attribute'] ?? null;

            // An action-less entry is a closure route, which cannot carry an
            // attribute; a class-less one comes from a vendor copy of
            // `openapi:inventory` older than this server.
            if ($class === null || $entry['action'] === null || ! $entry['documented']) {
                continue;
            }

            $counts[$class] = ($counts[$class] ?? 0) + 1;
            $actions[$class] ??= $entry['action'];
        }

        $conventions = [];

        foreach ($counts as $class => $count) {
            $conventions[] = new self((string) $class, $count, $actions[$class]);
        }

        usort($conventions, static fn (self $a, self $b): int => $b->count <=> $a->count);

        return $conventions;
    }

    /**
     * The project-local subclasses among them, most-used first. Uses of the
     * package's own attribute are not a local convention, so they drop out;
     * the first entry left, if any, is the convention to follow.
     *
     * @param  list<self>  $conventions
     * @return list<self>
     */
    public static function subclasses(array $conventions): array
    {
        return array_values(array_filter($conventions, static fn (self $convention): bool => $convention->isSubclass()));
    }

    /** @param  list<self>  $conventions */
    public static function documented(array $conventions): int
    {
        return array_sum(array_map(static fn (self $convention): int => $convention->count, $conventions));
    }

    public function isSubclass(): bool
    {
        return $this->class !== ApiSchema::class;
    }

    /** The bare class name, which is how the attribute is written at a call site. */
    public function shortName(): string
    {
        return basename(str_replace('\\', '/', $this->class));
    }

    /**
     * Relative to the application root when it sits beneath it, absolute
     * otherwise — a vendor-declared attribute is honestly reported as such.
     */
    public function file(): ?string
    {
        $file = $this->reflect()?->getFileName();

        if ($file === false || $file === null) {
            return null;
        }

        $base = rtrim(base_path(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        return str_starts_with($file, $base) ? substr($file, strlen($base)) : $file;
    }

    /** The constructor as written, so an agent can see what it has to pass. */
    public function signature(): ?string
    {
        $constructor = $this->constructor();

        return $constructor instanceof ReflectionMethod
            ? sprintf('__construct(%s)', implode(', ', array_map(
                static fn (ReflectionParameter $parameter): string => trim(sprintf(
                    '%s $%s',
                    (string) $parameter->getType(),
                    $parameter->getName(),
                )),
                $constructor->getParameters(),
            )))
            : null;
    }

    /**
     * Whether the constructor takes an OpenAPI fragment. A thin subclass does,
     * so the generic example applies with the name substituted; one taking a
     * route enum does not, and the generic example would not compile.
     */
    public function takesFragment(): bool
    {
        $type = ($this->constructor()?->getParameters()[0] ?? null)?->getType();

        return (string) $type === 'array';
    }

    private function constructor(): ?ReflectionMethod
    {
        return $this->reflect()?->getConstructor();
    }

    /** @return ReflectionClass<object>|null */
    private function reflect(): ?ReflectionClass
    {
        // The class name arrives from a subprocess's JSON, so it is a string
        // this process has no guarantee it can load.
        return class_exists($this->class) ? new ReflectionClass($this->class) : null;
    }
}
