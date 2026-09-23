<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\CodeCommandments\Py\ExprMatch;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\CoalescedLoopSubject;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A `for` that decides in its own header whether it was handed anything to walk — the twin of the
 * backend's {@see \JesseGall\CodeCommandments\Detectors\Backend\CoalescedLoopSubjectDetector}. Rooted at a
 * parameter, as there: a method defaulting its own state is a sparse registry answering "nobody", and a
 * call normalised with `or []` fixes absence at its source.
 */
final class CoalescedLoopSubjectDetector implements Detector
{
    public function sin(): Sin
    {
        return new CoalescedLoopSubject();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereExpression(static fn (Expr $expression): bool => $expression->fallback()->isSome())
            ->where(static fn (ExprMatch $match): bool => $match->isLoopSubject())
            ->where(static fn (ExprMatch $match): bool => $match->fallsBackToEmptyCollection())
            ->where(static fn (ExprMatch $match): bool => $match->fallbackReachesIntoParameter())
            ->get();
    }
}
