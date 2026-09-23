<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\CSharp;

use JesseGall\CodeCommandments\CSharp\Detector;
use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\NodeMatch;
use JesseGall\CodeCommandments\Sins\CSharp\RedundantElse;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * An `else` after an `if` branch that already left — the twin of the backend's and Python's
 * redundant-else rules. An `else if` chain is a ladder, left to the ladder rule.
 */
final class RedundantElseDetector implements Detector
{
    public function sin(): Sin
    {
        return new RedundantElse();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereStatement()
            ->where(static fn (NodeMatch $match): bool => $match->hasRedundantElse())
            ->get();
    }
}
