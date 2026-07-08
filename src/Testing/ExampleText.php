<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Testing;

/**
 * Text helpers shared by the fixture-example extractors ({@see FixtureExamples}, {@see VueFixtureExamples}):
 * pick the first non-empty source list under a set of candidate keys, and strip the common leading indent
 * off a block of lines. One home so the two engines' example extraction doesn't each keep a copy.
 */
final class ExampleText
{
    /**
     * The first non-empty `$sources[$key]` for the candidate $keys in order, else `[]`.
     *
     * @param  array<string, list<mixed>>  $sources
     * @param  list<string>  $keys
     * @return list<mixed>
     */
    public static function forKeys(array $sources, array $keys): array
    {
        foreach ($keys as $key) {
            if (! empty($sources[$key])) {
                return $sources[$key];
            }
        }

        return [];
    }

    /**
     * $lines with their common leading indentation removed (blank lines ignored when measuring).
     *
     * @param  list<string>  $lines
     */
    public static function dedent(array $lines): string
    {
        $min = PHP_INT_MAX;

        foreach ($lines as $line) {
            if (trim($line) !== '') {
                $min = min($min, strlen($line) - strlen(ltrim($line)));
            }
        }

        $min = $min === PHP_INT_MAX ? 0 : $min;

        return implode("\n", array_map(static fn (string $line): string => substr($line, $min), $lines));
    }
}
