<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi\Internal;

/**
 * An OpenAPI fragment rendered as the PHP an attribute holds it in.
 *
 * The inventory already carries every documented route's fragment, so a tool
 * asked "what does this project's convention look like" can print one instead
 * of telling an agent to go and read the class. That matters most for a
 * subclass keeping its fragments in a shared constant: the file grows with
 * every endpoint documented, so "read that file" gets more expensive the
 * further along the project is, while one entry stays the same size.
 *
 * `var_export()` would do the rendering, in a shape nobody writes by hand —
 * `array (` on a line of its own, keys printed for lists. An agent copies what
 * it is shown, so what it is shown has to look like the surrounding code.
 *
 * @internal
 */
final class Fragment
{
    /**
     * Lines worth printing. A fragment declaring a body schema per status runs
     * long, and the point of printing one is to spend less than reading the
     * class it came from.
     */
    private const int LINES = 40;

    /**
     * @param  array<mixed>  $fragment
     * @return string Short-array PHP, indented one level, every line comma-terminated — ready to paste into an existing array.
     */
    public static function render(array $fragment): string
    {
        $lines = self::lines($fragment, 1);

        if (count($lines) <= self::LINES) {
            return implode("\n", $lines);
        }

        return implode("\n", [
            ...array_slice($lines, 0, self::LINES),
            sprintf('    // ... %d more lines of the same shape, cut to keep this short.', count($lines) - self::LINES),
        ]);
    }

    /**
     * @param  array<mixed>  $value
     * @return list<string>
     */
    private static function lines(array $value, int $depth): array
    {
        $indent = str_repeat('    ', $depth);
        $list = array_is_list($value);
        $lines = [];

        foreach ($value as $key => $item) {
            // A list's keys are positions rather than names, and writing them
            // out is what makes `var_export()` output unusable as an example.
            $assignment = $list ? '' : self::key($key).' => ';

            if (is_array($item) && $item !== []) {
                $lines[] = $indent.$assignment.'[';
                $lines = [...$lines, ...self::lines($item, $depth + 1)];
                $lines[] = $indent.'],';

                continue;
            }

            $lines[] = $indent.$assignment.self::value($item).',';
        }

        return $lines;
    }

    /**
     * Status keys are the reason this has to handle an int. PHP coerces `'200'`
     * to `200` on the way in, and OpenAPI documents are written with the quoted
     * form, so it goes back out quoted.
     */
    private static function key(int|string $key): string
    {
        return self::quote((string) $key);
    }

    private static function value(mixed $value): string
    {
        return match (true) {
            $value === [] => '[]',
            is_string($value) => self::quote($value),
            is_bool($value) => $value ? 'true' : 'false',
            default => var_export($value, true),
        };
    }

    private static function quote(string $value): string
    {
        return "'".str_replace(['\\', "'"], ['\\\\', "\\'"], $value)."'";
    }
}
