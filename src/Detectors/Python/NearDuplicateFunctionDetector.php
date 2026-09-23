<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Codebase as BaseCodebase;
use JesseGall\CodeCommandments\Detectors\BucketsByGroupKey;
use JesseGall\CodeCommandments\Detectors\RecurrenceDetector;
use JesseGall\CodeCommandments\Located;
use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\NodeMatch;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\NearDuplicateFunction;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * Two-or-more Python functions with one control-flow skeleton that differ only in their local names or
 * their string/number literals — a type-2 clone, one function waiting for a parameter. The twin of the
 * TypeScript {@see \JesseGall\CodeCommandments\Detectors\Frontend\TypeScript\NearDuplicateFunctionDetector}.
 * Shapes that are alike by construction are left alone: an `__init__`, a one-statement body, a lookup
 * table and a stub.
 */
final class NearDuplicateFunctionDetector implements Detector, RecurrenceDetector
{
    use BucketsByGroupKey;

    /**
     * Minimum body weight — higher than the exact detector's, since a name-and-literal-blind match
     * collides by coincidence far more often in a small body.
     */
    private const int MIN_BODY_WEIGHT = 20;

    public function sin(): Sin
    {
        return new NearDuplicateFunction();
    }

    public function groupKey(Located $finding, BaseCodebase $codebase): ?string
    {
        return $finding instanceof NodeMatch && $finding->shapeHash() !== '' ? $finding->shapeHash() : null;
    }

    public function find(Codebase $codebase): array
    {
        $candidates = $codebase
            ->whereFunction()
            ->where(static fn (NodeMatch $match): bool => $match->bodyNodeCount() >= self::MIN_BODY_WEIGHT)
            ->reject(static fn (NodeMatch $match): bool => $match->isConstructorDeclaration())
            ->reject(static fn (NodeMatch $match): bool => $match->isSoleReturnExpression())
            ->reject(static fn (NodeMatch $match): bool => $match->isSoleExpressionStatement())
            ->reject(static fn (NodeMatch $match): bool => $match->isLiteralLookup())
            ->reject(static fn (NodeMatch $match): bool => $match->isStub())
            ->get();

        return $this->nearCopies($candidates, $codebase, static fn (NodeMatch $match): string => $match->bodyHash());
    }
}
