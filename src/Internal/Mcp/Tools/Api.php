<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi\Internal\Mcp\Tools;

use FilesystemIterator;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionEnum;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;
use SplFileInfo;
use UnitEnum;

/** @internal */
class Api extends Tool
{
    protected string $name = 'api';

    protected string $description = 'List the public API of the zero-to-prod/laravel-openapi package: every supported class rendered as a PHP stub with its public properties and method signatures. Classes under the Internal namespace or marked @internal are excluded.';

    private const string PREAMBLE = <<<'MARKDOWN'
        # Public API

        Every class below is part of the supported surface of the
        zero-to-prod/laravel-openapi package and follows SemVer. Anything not
        listed here is internal and may change in any release. Bodies are
        omitted; only signatures are shown.
        MARKDOWN;

    public function handle(): Response
    {
        return Response::text(self::render(dirname(__DIR__, 3), 'ZeroToProd\\LaravelOpenapi'));
    }

    /**
     * Render every public class under $directory as a PHP stub.
     *
     * @param  string  $directory  Root of a PSR-4 source tree
     * @param  string  $namespace  PSR-4 prefix that $directory maps to
     */
    public static function render(string $directory, string $namespace): string
    {
        $classes = self::classes($directory, $namespace);

        $stubs = array_map(self::stub(...), $classes);

        $total = array_sum(array_map(
            static fn (ReflectionClass $class): int => count(self::methods($class)),
            $classes,
        ));

        return implode("\n\n", [self::PREAMBLE, ...$stubs, 'Total public methods: '.$total])."\n";
    }

    /**
     * @return list<ReflectionClass<object>>
     */
    private static function classes(string $directory, string $namespace): array
    {
        $names = [];

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() === 'php') {
                $names[] = $namespace.'\\'.str_replace(DIRECTORY_SEPARATOR, '\\', substr($file->getPathname(), strlen($directory) + 1, -4));
            }
        }

        sort($names);

        $classes = [];

        foreach ($names as $name) {
            if (! class_exists($name) && ! interface_exists($name) && ! trait_exists($name)) {
                continue;
            }

            $class = new ReflectionClass($name);

            if (str_starts_with($name, $namespace.'\\Internal\\') || str_contains((string) $class->getDocComment(), '@internal')) {
                continue;
            }

            $classes[] = $class;
        }

        return $classes;
    }

    /** @param  ReflectionClass<object>  $class */
    private static function stub(ReflectionClass $class): string
    {
        $members = [
            ...array_map(self::property(...), self::properties($class)),
            ...array_map(self::method(...), self::methods($class)),
        ];

        return sprintf(
            "## %s\n\n%s```php\n%s\n{\n%s}\n```",
            $class->getName(),
            self::summary($class->getDocComment()),
            self::declaration($class),
            implode('', array_map(static fn (string $member): string => self::indent($member)."\n", $members)),
        );
    }

    /** @param  ReflectionClass<object>  $class */
    private static function declaration(ReflectionClass $class): string
    {
        $parent = $class->getParentClass();

        return implode(' ', array_filter([
            $class->isFinal() ? 'final' : '',
            $class->isAbstract() && ! $class->isInterface() ? 'abstract' : '',
            $class->isReadOnly() ? 'readonly' : '',
            self::keyword($class),
            self::shortName($class),
            $parent === false ? '' : 'extends '.$parent->getName(),
            $class->getInterfaceNames() === [] ? '' : 'implements '.implode(', ', $class->getInterfaceNames()),
        ]));
    }

    /** @param  ReflectionClass<object>  $class */
    private static function keyword(ReflectionClass $class): string
    {
        return match (true) {
            $class->isInterface() => 'interface',
            $class->isEnum() => 'enum',
            $class->isTrait() => 'trait',
            default => 'class',
        };
    }

    /**
     * The short name, carrying the backing type when the class is an enum.
     *
     * @param  ReflectionClass<object>  $class
     */
    private static function shortName(ReflectionClass $class): string
    {
        $name = $class->getName();
        $backing = is_a($name, UnitEnum::class, true) ? (new ReflectionEnum($name))->getBackingType() : null;

        return $backing instanceof ReflectionType ? $class->getShortName().': '.$backing : $class->getShortName();
    }

    /**
     * @param  ReflectionClass<object>  $class
     * @return list<ReflectionProperty>
     */
    private static function properties(ReflectionClass $class): array
    {
        return array_values(array_filter(
            $class->getProperties(ReflectionProperty::IS_PUBLIC),
            static fn (ReflectionProperty $property): bool => $property->getDeclaringClass()->getName() === $class->getName()
                && ! str_contains((string) $property->getDocComment(), '@internal'),
        ));
    }

    /**
     * @param  ReflectionClass<object>  $class
     * @return list<ReflectionMethod>
     */
    private static function methods(ReflectionClass $class): array
    {
        return array_values(array_filter(
            $class->getMethods(ReflectionMethod::IS_PUBLIC),
            static fn (ReflectionMethod $method): bool => $method->getDeclaringClass()->getName() === $class->getName()
                && ! str_contains((string) $method->getDocComment(), '@internal'),
        ));
    }

    private static function property(ReflectionProperty $property): string
    {
        return self::doc($property->getDocComment()).sprintf(
            'public %s%s%s$%s;',
            $property->isStatic() ? 'static ' : '',
            $property->isReadOnly() ? 'readonly ' : '',
            self::type($property->getType()),
            $property->getName(),
        );
    }

    private static function method(ReflectionMethod $method): string
    {
        $returnType = $method->getReturnType();

        return self::doc($method->getDocComment()).sprintf(
            'public %sfunction %s(%s)%s;',
            $method->isStatic() ? 'static ' : '',
            $method->getName(),
            implode(', ', array_map(self::parameter(...), $method->getParameters())),
            $returnType instanceof ReflectionType ? ': '.$returnType : '',
        );
    }

    private static function parameter(ReflectionParameter $parameter): string
    {
        return sprintf(
            '%s%s%s$%s%s',
            self::type($parameter->getType()),
            $parameter->isPassedByReference() ? '&' : '',
            $parameter->isVariadic() ? '...' : '',
            $parameter->getName(),
            $parameter->isDefaultValueAvailable() ? ' = '.self::value($parameter->getDefaultValue()) : '',
        );
    }

    /** Render a declared type with a trailing space, or nothing when untyped. */
    private static function type(?ReflectionType $type): string
    {
        return $type instanceof ReflectionType ? $type.' ' : '';
    }

    private static function value(mixed $value): string
    {
        return is_object($value)
            ? var_export($value, true)
            : json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    /** Collapse a doc comment onto one line, kept above the member it documents. */
    private static function doc(string|false $comment): string
    {
        if ($comment === false) {
            return '';
        }

        return preg_replace(['/\s*\n[ \t]*\*\/$/', '/\s*\n[ \t]*\*[ \t]?/'], [' */', ' '], $comment)."\n";
    }

    /** The prose of a class doc comment, dropping every annotation after it. */
    private static function summary(string|false $comment): string
    {
        $summary = trim(explode(' @', self::doc($comment))[0], "/* \n");

        return $summary === '' ? '' : $summary."\n\n";
    }

    private static function indent(string $member): string
    {
        return implode("\n", array_map(static fn (string $line): string => '    '.$line, explode("\n", $member)));
    }
}
