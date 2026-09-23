<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\Node;
use JesseGall\CodeCommandments\Cs\NodeMatch;
use JesseGall\CodeCommandments\CSharp\Detector;
use JesseGall\CodeCommandments\Sins\CSharp\CancelledCoalesce;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A fallback compared against the value it falls back to — the C# twin of the PHP cancelled-coalesce and
 * the Python cancelled-fallback rules.
 */
final class CancelledCoalesceDetector implements Detector
{
    public function sin(): Sin
    {
        return new CancelledCoalesce();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereExpression(static fn (Node $expression): bool => $expression->fallback()->isSome())
            ->where(static fn (NodeMatch $match): bool => $match->isComparedToItsFallback())
            ->get();
    }
}
