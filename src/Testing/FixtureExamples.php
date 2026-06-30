<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Testing;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Detectors\Detector;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;

/**
 * Extracts each detector's worked example from the fixture — the `#[Sinful]`-marked
 * declaration (the BAD half) and its `#[Righteous]` twin (the GOOD half) — as real,
 * parsed, tested source. The skill docs are generated from these, so a bad → good
 * example can never rot: it IS the fixture the detector is proven against.
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
        $righteous = self::sourcesByDetector($fixture, 'Righteous');

        $examples = [];

        foreach ($detectors as $detector) {
            $keys = [$detector->sin()::class, $detector::class, $detector->sin()->slug(), $detector->sin()->name()];
            $bad = self::forKeys($sinful, $keys);
            $good = self::forKeys($righteous, $keys);

            $examples[$detector::class] = self::pair($bad, $good);
        }

        return $examples;
    }

    /**
     * Pick the bad/good pair — preferring a Sinful and Righteous from the SAME class
     * (one coherent before/after), else the first of each.
     *
     * @param  list<array{class: string, source: string}>  $bad
     * @param  list<array{class: string, source: string}>  $good
     * @return array{bad: ?string, good: ?string}
     */
    private static function pair(array $bad, array $good): array
    {
        foreach ($bad as $b) {
            foreach ($good as $g) {
                if ($b['class'] === $g['class']) {
                    return ['bad' => $b['source'], 'good' => $g['source']];
                }
            }
        }

        return ['bad' => $bad[0]['source'] ?? null, 'good' => $good[0]['source'] ?? null];
    }

    /**
     * Every marked declaration under any of the given detector keys.
     *
     * @param  array<string, list<array{class: string, source: string}>>  $sources
     * @param  list<string>  $keys
     * @return list<array{class: string, source: string}>
     */
    private static function forKeys(array $sources, array $keys): array
    {
        foreach ($keys as $key) {
            if (! empty($sources[$key])) {
                return $sources[$key];
            }
        }

        return [];
    }

    /**
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
     */
    private static function declarationSource(NodeMatch $match): string
    {
        $node = $match->enclosingFunction() ?? $match->enclosingClass() ?? $match->node;
        $lines = file($match->file->path) ?: [];
        $slice = array_slice($lines, $node->getStartLine() - 1, $node->getEndLine() - $node->getStartLine() + 1);

        $kept = array_filter(
            array_map(static fn (string $line): string => rtrim($line, "\n"), $slice),
            static fn (string $line): bool => ! str_contains($line, '#[Sinful(') && ! str_contains($line, '#[Righteous('),
        );

        return self::dedent(array_values($kept));
    }

    /**
     * Strip the common leading indentation from a block (so a fixture method reads as a
     * top-level snippet).
     *
     * @param  list<string>  $lines
     */
    private static function dedent(array $lines): string
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
