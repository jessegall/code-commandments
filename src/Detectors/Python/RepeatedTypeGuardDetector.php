<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Codebase as BaseCodebase;
use JesseGall\CodeCommandments\Detectors\BucketsByGroupKey;
use JesseGall\CodeCommandments\Detectors\RecurrenceDetector;
use JesseGall\CodeCommandments\Located;
use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\CodeCommandments\Py\ExprMatch;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\RepeatedTypeGuard;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * One `isinstance` narrowing written in two places — the twin of the backend's
 * {@see \JesseGall\CodeCommandments\Detectors\Backend\RepeatedTypeGuardDetector}.
 */
final class RepeatedTypeGuardDetector implements Detector, RecurrenceDetector
{
    use BucketsByGroupKey;

    public function sin(): Sin
    {
        return new RepeatedTypeGuard();
    }

    public function groupKey(Located $finding, BaseCodebase $codebase): ?string
    {
        return $finding instanceof ExprMatch && $finding->isTypeNarrowingGuard() ? $finding->guardFingerprint() : null;
    }

    public function find(Codebase $codebase): array
    {
        $guards = $codebase
            ->whereExpression(static fn (Expr $expression): bool => $expression->isAnd())
            ->where(static fn (ExprMatch $match): bool => $match->isTypeNarrowingGuard())
            ->get();

        return array_merge([], ...$this->recurringBuckets($guards, $codebase));
    }
}
