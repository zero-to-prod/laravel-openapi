<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi\Internal;

use ReflectionClass;
use ReflectionClassConstant;
use ReflectionMethod;
use ReflectionParameter;
use ZeroToProd\LaravelOpenapi\ApiSchema;

/**
 * @internal
 *
 * @phpstan-type Counted array{action: string|null, documented: bool, attribute?: string|null, methods?: list<string>, schema?: array<string, mixed>}
 */
final readonly class LocalConvention
{
    /**
     * @param  string  $class  The attribute class, as reported by the inventory.
     * @param  int  $count  Documented routes in scope carrying it.
     * @param  string  $action  One of them, as a call site worth reading.
     * @param  array<string, mixed>  $paths  That call site's declared paths, as the example to follow.
     */
    private function __construct(
        public string $class,
        public int $count,
        public string $action,
        public array $paths,
    ) {}

    /**
     * @param  list<Counted>  $entries
     * @param  list<string>  $prefer  HTTP methods the work still to do uses. The example reported for a
     *                                class is one of its routes sharing a method with them where there
     *                                is one, because that entry's statuses and body are the closest to
     *                                what is about to be written.
     * @return list<self>
     */
    public static function all(array $entries, array $prefer = []): array
    {
        $counts = [];
        $actions = [];
        $paths = [];
        $nearest = [];

        foreach ($entries as $entry) {
            $class = $entry['attribute'] ?? null;
            if ($class === null || $entry['action'] === null || ! $entry['documented']) {
                continue;
            }

            $counts[$class] = ($counts[$class] ?? 0) + 1;
            $closer = array_intersect($entry['methods'] ?? [], $prefer) !== [];

            if (! isset($actions[$class]) || $closer && ! $nearest[$class]) {
                $actions[$class] = $entry['action'];
                $paths[$class] = self::pathsOf($entry);
                $nearest[$class] = $closer;
            }
        }

        $conventions = [];

        foreach ($counts as $class => $count) {
            $conventions[] = new self((string) $class, $count, $actions[$class], $paths[$class]);
        }

        usort($conventions, static fn (self $a, self $b): int => $b->count <=> $a->count);

        return $conventions;
    }

    /**
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

    /** @return list<string> */
    public function constants(): array
    {
        $names = [];

        foreach ($this->reflect()?->getReflectionConstants(ReflectionClassConstant::IS_PUBLIC) ?? [] as $constant) {
            if (is_array($constant->getValue()) && $constant->getDeclaringClass()->getName() !== ApiSchema::class) {
                $names[] = $constant->getName();
            }
        }

        return $names;
    }

    public function storage(): ?string
    {
        $constants = $this->constants();

        return $constants === [] ? null : sprintf(
            'It takes no OpenAPI fragment of its own: the fragments live in %s, which the constructor '
            .'merges. Add yours there — and do not read the whole class, it carries every route '
            .'documented so far.',
            implode(', ', array_map(static fn (string $name): string => 'const '.$name, $constants)),
        );
    }

    public function fragment(): ?string
    {
        return $this->paths === [] ? null : Fragment::render($this->paths);
    }

    public function indirect(): bool
    {
        return $this->signature() !== null && ! $this->takesFragment();
    }

    public function takesFragment(): bool
    {
        $type = ($this->constructor()?->getParameters()[0] ?? null)?->getType();

        return (string) $type === 'array';
    }

    /**
     * @param  Counted  $entry
     * @return array<string, mixed>
     */
    private static function pathsOf(array $entry): array
    {
        $paths = ($entry['schema'] ?? [])['paths'] ?? null;

        return is_array($paths) ? $paths : [];
    }

    private function constructor(): ?ReflectionMethod
    {
        return $this->reflect()?->getConstructor();
    }

    /** @return ReflectionClass<object>|null */
    private function reflect(): ?ReflectionClass
    {
        return class_exists($this->class) ? new ReflectionClass($this->class) : null;
    }
}
