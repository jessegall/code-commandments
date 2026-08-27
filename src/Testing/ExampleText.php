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
     */
    public static function pair(array $bad, array $good, string $key): Example
    {
        $resolution = self::counterpart($bad, $good, $key);
        $sinful = self::answered($bad, $resolution, $key);

        return new Example(new Comparison($sinful['source'] ?? null, $resolution['source'] ?? null));
    }

    /**
     * The resolutions that together form ONE fix: the {@see counterpart} of the sinful code first, then
     * every other resolution marked in its FILE, in the order they were marked.
     *
     * A fix that MOVES behaviour has two ends — the call site that got thinner and the type that
     * received the method — and publishing only the end that shrank teaches a reader to call a method
     * that does not exist. The file is what says the ends belong together: a fixture file is one
     * scenario, so everything marked as this sin's resolution in it is part of the same repair. The
     * counterpart leads so the good half still lines up against the bad one as a before/after.
     *
     * @param  list<array<string, mixed>>  $bad
     * @param  list<array<string, mixed>>  $good
     * @return list<array<string, mixed>>
     */
    public static function resolution(array $bad, array $good, string $key): array
    {
        $counterpart = self::counterpart($bad, $good, $key);

        if ($counterpart === null) {
            return [];
        }

        $collaborators = [];

        foreach ($good as $one) {
            if ($one !== $counterpart && $one['file'] === $counterpart['file']) {
                $collaborators[] = $one;
            }
        }

        return [$counterpart, ...$collaborators];
    }

    /**
     * The resolution that answers one of the $bad — the first found scanning the sinful in order, so the
     * pair is one coherent before/after — falling back to the first resolution of all.
     *
     * @param  list<array<string, mixed>>  $bad
     * @param  list<array<string, mixed>>  $good
     * @return ?array<string, mixed>
     */
    private static function counterpart(array $bad, array $good, string $key): ?array
    {
        foreach ($bad as $one) {
            foreach ($good as $other) {
                if ($one[$key] === $other[$key]) {
                    return $other;
                }
            }
        }

        return $good[0] ?? null;
    }

    /**
     * The sinful declaration the given resolution repairs — the one sharing its $key — falling back to
     * the first marked of all.
     *
     * @param  list<array<string, mixed>>  $bad
     * @param  ?array<string, mixed>  $resolution
     * @return ?array<string, mixed>
     */
    private static function answered(array $bad, ?array $resolution, string $key): ?array
    {
        foreach ($resolution === null ? [] : $bad as $one) {
            if ($one[$key] === $resolution[$key]) {
                return $one;
            }
        }

        return $bad[0] ?? null;
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
        // `explode` always yields at least one element, so the first line is never absent.
        $lines = explode("\n", ltrim($source, "\n"));

        if (trim($lines[0]) !== '/**') {
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
     * Several occurrences shown as ONE example, each headed by where it lives.
     *
     * Some sins are a relationship rather than a property: a duplicate shown once is not a
     * duplicate, and a reader given one copy has no way to see what it is a copy OF. So every member
     * of the group is shown, and named, because the point is that they are in different places.
     *
     * Each block wears the `heading` its extractor wrote — a finished comment line in that block's own
     * language, so a group mixing a template with the module beside it is commented correctly either
     * way. A `null` heading prints the block bare: a marked class or interface opens by naming itself,
     * and a line saying so above it is the same word twice.
     *
     * @param  list<array{source: string, heading: ?string, ...}>  $occurrences
     */
    public static function group(array $occurrences, bool $lift): string
    {
        $blocks = [];

        foreach ($occurrences as $occurrence) {
            $source = $lift ? self::lifted($occurrence['source']) : $occurrence['source'];
            $blocks[] = $occurrence['heading'] === null ? $source : "{$occurrence['heading']}\n{$source}";
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
