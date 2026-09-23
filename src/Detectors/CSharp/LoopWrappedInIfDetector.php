<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\CSharp;

use JesseGall\CodeCommandments\CSharp\Detector;
use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\NodeMatch;
use JesseGall\CodeCommandments\Sins\CSharp\LoopWrappedInIf;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A loop whose whole body is one `if` around real work — the C# twin of the backend's and Python's
 * loop-wrapped-in-if rules. A one-statement filter and a search that leaves the loop are left alone.
 */
final class LoopWrappedInIfDetector implements Detector
{
    public function sin(): Sin
    {
        return new LoopWrappedInIf();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereStatement()
            ->where(static fn (NodeMatch $match): bool => $match->isSoleLoopBodyGuard())
            ->get();
    }
}
