<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Doctrines;

/**
 * The cascade: within one doctrine, a finding is SUPPRESSED when a STRICTLY
 * COARSER (lower-band) finding of the SAME doctrine sits in the SAME method region
 * (overlapping line ranges) of the same file. So the architectural root is shown
 * first and the finer symptom only surfaces once that root is gone.
 *
 * Suppression is strictly INTRA-doctrine — two different doctrines that both fire
 * in one region are both shown (no invented global altitude). Singletons (no
 * doctrine) never suppress and are never suppressed. Region is the enclosing
 * METHOD, not a fixed line window, so a coarse finding never silences a finer one
 * in a different method that merely sits nearby.
 */
final class DoctrineCascade
{
    /**
     * @param  list<Ranked>  $findings
     * @return list<Ranked>  the survivors, in input order
     */
    public static function apply(array $findings): array
    {
        // Bucket by file: a finding can only be suppressed by another in the same
        // file, so the O(n^2) scan runs per-file.
        $byPath = [];

        foreach ($findings as $finding) {
            $byPath[$finding->path][] = $finding;
        }

        $kept = [];

        foreach ($findings as $finding) {
            if (! self::isSuppressed($finding, $byPath[$finding->path])) {
                $kept[] = $finding;
            }
        }

        return $kept;
    }

    /**
     * @param  list<Ranked>  $sameFile
     */
    private static function isSuppressed(Ranked $finding, array $sameFile): bool
    {
        if ($finding->doctrine === null || $finding->band === null) {
            return false; // a singleton is never suppressed
        }

        foreach ($sameFile as $other) {
            if ($other === $finding) {
                continue;
            }

            if ($other->doctrine !== $finding->doctrine || $other->band === null) {
                continue; // only a same-doctrine member can suppress
            }

            if ($other->band >= $finding->band) {
                continue; // must be STRICTLY coarser (lower band index)
            }

            if (self::regionsOverlap($other, $finding)) {
                return true;
            }
        }

        return false;
    }

    private static function regionsOverlap(Ranked $a, Ranked $b): bool
    {
        return $a->startLine <= $b->endLine && $b->startLine <= $a->endLine;
    }
}
