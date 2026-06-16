<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use JesseGall\CodeCommandments\Results\Finding;

/**
 * Orders a flat list of findings for one-at-a-time resolution and applies
 * region-scoped deferral so a symptom is never presented while its root
 * cause is still unresolved in the same place.
 *
 * Deferral: if prophet P declares it `supersedes` prophet Q, then a Q
 * finding is held back whenever a P finding sits in the same file within a
 * line window. Fix the P finding (the root cause), re-judge, and the Q
 * symptom is usually gone — and if it survives, it surfaces on the next
 * pass. A Q finding elsewhere in the file, away from any P finding, still
 * surfaces normally.
 *
 * Ordering of what remains: tier weight (structural first) → file → line →
 * prophet, so the most root-cause work is presented first and the walk is
 * stable.
 */
final class FindingQueue
{
    /**
     * Findings within this many lines of a superseding finding (same file)
     * are treated as the same region and deferred. A null line on either
     * side is treated as "whole file".
     */
    private const DEFER_WINDOW = 60;

    /**
     * @param  list<Finding>  $findings
     * @return list<Finding>
     */
    public static function order(array $findings): array
    {
        $kept = array_values(array_filter(
            $findings,
            static fn (Finding $f) => ! self::isSuperseded($f, $findings),
        ));

        usort($kept, static function (Finding $a, Finding $b): int {
            // Auto-fixable findings first — one `repent` clears them all, so
            // get them out of the way before anything that needs hand work.
            // Then sins (blocking) before warnings; then most root-cause tier;
            // then a stable file/line/prophet walk.
            return [$a->autoFixable ? 0 : 1, $a->isSin() ? 0 : 1, $a->tier->weight(), $a->relativePath, $a->line ?? PHP_INT_MAX, $a->prophetClass]
                <=> [$b->autoFixable ? 0 : 1, $b->isSin() ? 0 : 1, $b->tier->weight(), $b->relativePath, $b->line ?? PHP_INT_MAX, $b->prophetClass];
        });

        return $kept;
    }

    /**
     * @param  list<Finding>  $all
     */
    private static function isSuperseded(Finding $finding, array $all): bool
    {
        foreach ($all as $other) {
            if ($other === $finding) {
                continue;
            }

            if ($other->relativePath !== $finding->relativePath) {
                continue;
            }

            if (! in_array($finding->prophetClass, $other->supersedes, true)) {
                continue;
            }

            if (self::sameRegion($other->line, $finding->line)) {
                return true;
            }
        }

        return false;
    }

    private static function sameRegion(?int $a, ?int $b): bool
    {
        if ($a === null || $b === null) {
            return true;
        }

        return abs($a - $b) <= self::DEFER_WINDOW;
    }
}
