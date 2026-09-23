<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\CSharp;

use JesseGall\CodeCommandments\Codebase as BaseCodebase;
use JesseGall\CodeCommandments\CSharp\Detector;
use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\NodeMatch;
use JesseGall\CodeCommandments\Detectors\BucketsByGroupKey;
use JesseGall\CodeCommandments\Detectors\RecurrenceDetector;
use JesseGall\CodeCommandments\Located;
use JesseGall\CodeCommandments\Sins\CSharp\NearDuplicateMethod;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * Two-or-more C# members with one control-flow skeleton that differ only in their local names or their
 * string, number and character literals — a type-2 clone, one method waiting for a parameter. The twin
 * of the Python and TypeScript near-duplicate rules. Shapes that are alike by construction are left
 * alone: a constructor, a one-statement body (an expression body included), a lookup table, a stub, and
 * an override or implementation whose shape the contract dictates.
 */
final class NearDuplicateMethodDetector implements Detector, RecurrenceDetector
{
    use BucketsByGroupKey;

    /**
     * Minimum body weight — higher than the exact detector's, since a name-and-literal-blind match
     * collides by coincidence far more often in a small body.
     */
    private const int MIN_BODY_WEIGHT = 20;

    public function sin(): Sin
    {
        return new NearDuplicateMethod();
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
            ->reject(static fn (NodeMatch $match): bool => $match->isOverride())
            ->get();

        return $this->nearCopies($candidates, $codebase, static fn (NodeMatch $match): string => $match->bodyHash());
    }
}
