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
     * The first bad/good example sharing the same $key (e.g. `class` or `file`), so a detector's before/
     * after pair comes from ONE scenario; falls back to the first of each when none share a key.
     *
     * @param  list<array<string, mixed>>  $bad
     * @param  list<array<string, mixed>>  $good
     * @return array{bad: mixed, good: mixed}
     */
    public static function pair(array $bad, array $good, string $key): array
    {
        foreach ($bad as $b) {
            foreach ($good as $g) {
                if ($b[$key] === $g[$key]) {
                    return ['bad' => $b['source'], 'good' => $g['source']];
                }
            }
        }

        return ['bad' => $bad[0]['source'] ?? null, 'good' => $good[0]['source'] ?? null];
    }

    /**
     * A marked declaration rewritten for the docs: its leading docblock becomes plain `//` lines
     * ABOVE the snippet, and the `@param`/`@return` tags go.
     *
     * The docblock on a fixture is two things at once — an explanation written for the reader of
     * this skill, and PHP the reader has to look past to reach the code. Lifting it keeps the
     * explanation and drops the ceremony, so the snippet under it is only ever the code the example
     * is about. Where the docblock IS the subject (the sins about documentation itself) the caller
     * asks for it verbatim instead: lifting it there would delete the thing being taught.
     */
    public static function lifted(string $source): string
    {
        $lines = explode("\n", ltrim($source, "\n"));

        if (trim($lines[0] ?? '') !== '/**') {
            return $source;
        }

        $prose = [];
        $at = 1;

        for (; $at < count($lines); $at++) {
            $line = trim($lines[$at]);

            if ($line === '*/') {
                $at++;

                break;
            }

            $line = trim(ltrim($line, '*'));

            // A tag documents the SIGNATURE, which the snippet states for itself.
            if ($line !== '' && ! str_starts_with($line, '@')) {
                $prose[] = "// {$line}";
            }
        }

        $code = array_slice($lines, $at);

        return $prose === [] ? implode("\n", $code) : implode("\n", [...$prose, '', ...$code]);
    }

    /**
     * Both halves of a pair lifted, when this skill's examples want lifting.
     *
     * @param  array{bad: mixed, good: mixed}  $pair
     * @return array{bad: ?string, good: ?string}
     */
    public static function liftedPair(array $pair, bool $lift): array
    {
        return [
            'bad' => is_string($pair['bad']) && $lift ? self::lifted($pair['bad']) : $pair['bad'],
            'good' => is_string($pair['good']) && $lift ? self::lifted($pair['good']) : $pair['good'],
        ];
    }

    /**
     * Several occurrences shown as ONE example, each headed by where it lives.
     *
     * Some sins are a relationship rather than a property: a duplicate shown once is not a
     * duplicate, and a reader given one copy has no way to see what it is a copy OF. So every member
     * of the group is shown, and named, because the point is that they are in different places.
     *
     * @param  list<array<string, string>>  $occurrences
     * @param  string  $key  which field names the place — the class on one engine, the file on the other
     * @param  string  $open  how a comment opens in the language being shown, and $close how it ends
     */
    public static function group(array $occurrences, string $key, bool $lift, string $open = '//', string $close = ''): string
    {
        $blocks = [];

        foreach ($occurrences as $occurrence) {
            $source = $lift ? self::lifted($occurrence['source']) : $occurrence['source'];
            $blocks[] = "{$open} in {$occurrence[$key]}{$close}\n{$source}";
        }

        return implode("\n\n", $blocks);
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
