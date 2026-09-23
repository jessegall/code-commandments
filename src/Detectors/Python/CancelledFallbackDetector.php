<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\CodeCommandments\Py\ExprMatch;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\CancelledFallback;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A default compared against itself — the twin of the backend's
 * {@see \JesseGall\CodeCommandments\Detectors\Backend\CancelledCoalesceDetector}. As there, a `None`
 * fallback is the absence itself and an empty collection is "no items", so only an empty scalar counts.
 */
final class CancelledFallbackDetector implements Detector
{
    public function sin(): Sin
    {
        return new CancelledFallback();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereExpression(static fn (Expr $expression): bool => $expression->fallback()->isSomeAnd(static fn (Expr $fallback): bool => $fallback->isEmptyScalar()))
            ->where(static fn (ExprMatch $match): bool => $match->isComparedToItsFallback())
            ->get();
    }
}
