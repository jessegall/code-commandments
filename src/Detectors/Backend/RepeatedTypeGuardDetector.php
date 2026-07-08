<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Backend\RepeatedTypeGuard;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * Flags a multi-`instanceof` type-narrowing guard (`$x instanceof A && $x->y instanceof B`) that recurs
 * VERBATIM in ≥2 places — a check copied instead of named. Buckets the guards by their exact structural
 * fingerprint (so different guards never collide) and reports every occurrence of a bucket seen ≥2×. The
 * fix is a named predicate. A one-off chain is fine and is not flagged.
 */
final class RepeatedTypeGuardDetector implements Detector
{
    public function sin(): Sin
    {
        return new RepeatedTypeGuard();
    }

    public function find(Codebase $codebase): array
    {
        $byFingerprint = [];

        foreach ($codebase->where(static fn (AstNode $node): bool => $node->isTypeNarrowingGuard())->get() as $match) {
            $byFingerprint[$match->canonicalGuardHash()][] = $match;
        }

        $findings = [];

        foreach ($byFingerprint as $occurrences) {
            if (count($occurrences) >= 2) {
                array_push($findings, ...$occurrences);
            }
        }

        return $findings;
    }
}
