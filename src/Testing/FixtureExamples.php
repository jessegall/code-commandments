<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Testing;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Detectors\RecurrenceDetector;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;

/**
 * Extracts each detector's worked example from the fixture — the `#[Sinful]`-marked declaration
 * (the BAD half) and its resolution (the GOOD half) — as real, parsed, tested source. The skill
 * docs are generated from these, so a bad → good example can never rot: it IS the fixture the
 * detector is proven against. The good half comes from {@see Fixed} where one exists and falls back
 * to {@see Righteous} only where none does — a stopgap, not a design, since a righteous twin is a
 * look-alike the detector must not flag (usually an EXEMPTION) rather than the bad code repaired.
 */
final class FixtureExamples
{
    /**
     * @param  list<Detector>  $detectors
     * @return array<class-string<Detector>, array{bad: ?string, good: ?string}>
     */
    public static function extract(Codebase $fixture, array $detectors): array
    {
        $sinful = self::sourcesByDetector($fixture, 'Sinful');
        $fixed = self::sourcesByDetector($fixture, 'Fixed');
        $righteous = self::sourcesByDetector($fixture, 'Righteous');

        $examples = [];

        foreach ($detectors as $detector) {
            $keys = [$detector->sin()::class, $detector::class, $detector->sin()->slug(), $detector->sin()->name()];
            $bad = ExampleText::forKeys($sinful, $keys);
            $good = ExampleText::forKeys($fixed, $keys) ?: ExampleText::forKeys($righteous, $keys);
            $lift = ! $detector->sin()->skill()->examplesKeepDocblocks();

            $pair = ExampleText::liftedPair(ExampleText::pair($bad, $good, 'class'), $lift);

            // A RECURRENCE sin is a relationship, not a property: its example is the whole GROUP, or
            // it shows a duplicate with nothing to be a duplicate of.
            if ($detector instanceof RecurrenceDetector && count($bad) > 1) {
                $pair['bad'] = ExampleText::group($bad, 'class', $lift);
            }

            $examples[$detector::class] = $pair;
        }

        return $examples;
    }

    /**
     * Which sins have a real RESOLUTION in the fixture, and which are still falling back to a
     * righteous look-alike — the coverage the enforcement test reads, and the answer to "is this
     * skill's good example actually the fix?".
     *
     * @param  list<Detector>  $detectors
     * @return list<class-string<Detector>>  the detectors with no `#[Fixed]` twin
     */
    public static function withoutResolution(Codebase $fixture, array $detectors): array
    {
        $fixed = self::sourcesByDetector($fixture, 'Fixed');
        $missing = [];

        foreach ($detectors as $detector) {
            $keys = [$detector->sin()::class, $detector::class, $detector->sin()->slug(), $detector->sin()->name()];

            if (ExampleText::forKeys($fixed, $keys) === []) {
                $missing[] = $detector::class;
            }
        }

        return $missing;
    }

    /**
     * Pick the bad/good pair — preferring a Sinful and Righteous from the SAME class
     * (one coherent before/after), else the first of each.
     *
     * @param  list<array{class: string, source: string}>  $bad
     * @param  list<array{class: string, source: string}>  $good
     * @return array{bad: ?string, good: ?string}
     *
     * Every marked declaration under any of the given detector keys.
     *
     * @param  array<string, list<array{class: string, source: string}>>  $sources
     * @param  list<string>  $keys
     * @return list<array{class: string, source: string}>
     *
     * Every marked declaration's class + source, grouped by the detector identifier the
     * marker names.
     *
     * @return array<string, list<array{class: string, source: string}>>
     */
    private static function sourcesByDetector(Codebase $fixture, string $attribute): array
    {
        $sources = [];

        foreach ($fixture->whereAttribute($attribute)->get() as $match) {
            $detector = self::detector($match);

            if ($detector !== null) {
                $sources[$detector][] = ['class' => $match->enclosingClassName() ?? $match->file->path, 'source' => self::declarationSource($match)];
            }
        }

        return $sources;
    }

    /**
     * The source of the declaration the attribute decorates — the tightest one (the
     * method if it's on a method, else the class) — dedented, with the marker attribute
     * lines removed so only the example code shows.
     *
     * The slice starts at the DOCBLOCK, not the declaration. A node's start line excludes its doc
     * comment, and for a whole family of sins the docblock IS the subject: cut it away and
     * `ceremony-docblock` published a bad and a good example that differed only in the method's
     * name, with the thing being taught deleted from both.
     */
    private static function declarationSource(NodeMatch $match): string
    {
        $node = $match->enclosingFunction() ?? $match->enclosingClass() ?? $match->node;
        $lines = file($match->file->path) ?: [];
        $start = min($node->getDocComment()?->getStartLine() ?? $node->getStartLine(), $node->getStartLine());
        $slice = array_slice($lines, $start - 1, $node->getEndLine() - $start + 1);

        $kept = array_filter(
            array_map(static fn (string $line): string => rtrim($line, "\n"), $slice),
            static fn (string $line): bool => ! str_contains($line, '#[Sinful(')
                && ! str_contains($line, '#[Righteous(')
                && ! str_contains($line, '#[Fixed('),
        );

        return ExampleText::dedent(array_values($kept));
    }

    /**
     * Strip the common leading indentation from a block (so a fixture method reads as a
     * top-level snippet).
     *
     * @param  list<string>  $lines
     */

    private static function detector(NodeMatch $match): ?string
    {
        $args = $match->arguments();
        $value = $args[0]->value ?? null;

        if ($value instanceof ClassConstFetch && $value->class instanceof Name) {
            return $value->class->toString();
        }

        if ($value instanceof String_) {
            return $value->value;
        }

        return null;
    }
}
