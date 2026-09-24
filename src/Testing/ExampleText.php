<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Testing;

use Closure;

/**
 * Text helpers shared by the fixture-example extractors ({@see FixtureExamples}, {@see VueFixtureExamples}):
 * pick the first non-empty source list under a set of candidate keys, and strip the common leading indent
 * off a block of lines. One home so no engine's example extraction keeps a copy of its own.
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
     * The first bad/good example from ONE scenario, so a detector's before/after is one piece of code
     * repaired; falls back to the first of each when no two share one.
     *
     * @param  list<MarkedSource>  $bad
     * @param  list<MarkedSource>  $good
     */
    public static function pair(array $bad, array $good): Example
    {
        $resolution = self::counterpart($bad, $good);
        $sinful = self::answered($bad, $resolution);

        return new Example(new Comparison($sinful?->source, $resolution?->source));
    }

    /**
     * The sinful source the example is anchored on — the one the resolution answers, else the first.
     *
     * @param  list<MarkedSource>  $bad
     * @param  list<MarkedSource>  $good
     */
    public static function anchor(array $bad, array $good): ?MarkedSource
    {
        return self::answered($bad, self::counterpart($bad, $good));
    }

    /**
     * The $sources in the one recurring group $anchor belongs to — a recurrence rule's Bad is a group, and
     * every OTHER group the fixture marks is another scenario its Good does not answer. $groups is each
     * finding's group, by file and line; a source is in the group of a finding inside the lines it shows.
     *
     * @param  list<MarkedSource>  $sources
     * @param  array<string, array<int, string>>  $groups
     * @return list<MarkedSource>
     */
    public static function recurring(array $sources, MarkedSource $anchor, array $groups): array
    {
        $group = self::groupOf($anchor, $groups);

        if ($group === null) {
            return $sources;
        }

        return array_values(array_filter($sources, static fn (MarkedSource $source): bool => self::groupOf($source, $groups) === $group));
    }

    /**
     * @param  array<string, array<int, string>>  $groups
     */
    private static function groupOf(MarkedSource $source, array $groups): ?string
    {
        if (! isset($groups[$source->file])) {
            return null;
        }

        foreach ($groups[$source->file] as $line => $group) {
            if ($source->shows($source->file, $line)) {
                return $group;
            }
        }

        return null;
    }

    /**
     * The resolutions that together form ONE fix: the {@see counterpart} of the sinful code first, then
     * every other resolution marked in its FILE, in the order they were marked.
     *
     * A fix that MOVES behaviour has two ends — the call site that got thinner and the type that
     * received the method — and publishing only the end that shrank teaches a reader to call a method
     * that does not exist. The counterpart leads, so the good half still lines up against the bad one
     * as a before/after, and two kinds of declaration follow it:
     *
     * - what is marked in the counterpart's OWN file — the interface pulled up beside it, the sibling
     *   the behaviour moved onto;
     * - what is marked in a file holding no sinful code of this sin — a SHARED collaborator, like the
     *   model that grew the named transition or the Null Object a whole family of fixes defaults to.
     *
     * The sinful marker is what anchors a scenario to a file, which is what keeps a sin fixed three
     * separate times (`DivergentTwin`) from publishing all three repairs as one.
     *
     * @param  list<MarkedSource>  $bad
     * @param  list<MarkedSource>  $good
     * @return list<MarkedSource>
     */
    public static function resolution(array $bad, array $good): array
    {
        $counterpart = self::counterpart($bad, $good);

        if ($counterpart === null) {
            return [];
        }

        $collaborators = array_filter(
            $good,
            static fn (MarkedSource $one): bool => $one !== $counterpart
                && ($one->sharesFileWith($counterpart) || ! array_any($bad, static fn (MarkedSource $sinful): bool => $sinful->sharesFileWith($one))),
        );

        return [$counterpart, ...array_values($collaborators)];
    }

    /**
     * The resolution that answers one of the $bad — the first found scanning the sinful in order, so the
     * pair is one coherent before/after. A resolution in the same scenario answers first, then one in the
     * same file, since a fixture file holds one scenario per rule; only then the first of all.
     *
     * @param  list<MarkedSource>  $bad
     * @param  list<MarkedSource>  $good
     */
    private static function counterpart(array $bad, array $good): ?MarkedSource
    {
        foreach (self::sharing() as $shared) {
            foreach ($bad as $one) {
                foreach ($good as $other) {
                    if ($shared($one, $other)) {
                        return $other;
                    }
                }
            }
        }

        return $good[0] ?? null;
    }

    /**
     * The sinful source the given resolution repairs — the one in its scenario, else its file — falling
     * back to the first marked of all.
     *
     * @param  list<MarkedSource>  $bad
     */
    private static function answered(array $bad, ?MarkedSource $resolution): ?MarkedSource
    {
        foreach ($resolution === null ? [] : self::sharing() as $shared) {
            foreach ($bad as $one) {
                if ($shared($one, $resolution)) {
                    return $one;
                }
            }
        }

        return $bad[0] ?? null;
    }

    /**
     * What makes a Bad and a Good one before/after, strongest first: the same scenario, then the same file.
     *
     * @return list<Closure(MarkedSource, MarkedSource): bool>
     */
    private static function sharing(): array
    {
        return [
            static fn (MarkedSource $one, MarkedSource $other) => $one->sharesScenarioWith($other),
            static fn (MarkedSource $one, MarkedSource $other) => $one->sharesFileWith($other),
        ];
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
     * and a line saying so above it is the same word twice. A block another block already shows whole —
     * a marked method of a marked class — is not shown twice.
     *
     * @param  list<MarkedSource>  $sources
     */
    public static function group(array $sources, bool $lift): string
    {
        $blocks = [];
        $flat = array_map(static fn (MarkedSource $source) => self::flattened($source->source), $sources);

        foreach ($sources as $source) {
            $mine = self::flattened($source->source);

            if (array_any($flat, static fn (string $other): bool => $other !== $mine && str_contains($other, $mine))) {
                continue;
            }

            $text = $lift ? self::lifted($source->source) : $source->source;
            $blocks[] = $source->heading === null ? $text : "{$source->heading}\n{$text}";
        }

        return implode("\n\n", $blocks);
    }

    /**
     * $source with every run of whitespace as one space — what two blocks are compared by, so a method
     * reads the same dedented alone as indented inside its class.
     */
    private static function flattened(string $source): string
    {
        return (string) preg_replace('/\s+/', ' ', trim($source));
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
