<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Frontend\TypeScript;

use JesseGall\CodeCommandments\Codebase as BaseCodebase;
use JesseGall\CodeCommandments\Detectors\BucketsByGroupKey;
use JesseGall\CodeCommandments\Detectors\RecurrenceDetector;
use JesseGall\CodeCommandments\Frontend\Detector;
use JesseGall\CodeCommandments\Located;
use JesseGall\CodeCommandments\Sins\Frontend\TypeScript\NearDuplicateFunction;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Ts\NodeMatch;
use JesseGall\CodeCommandments\Vue\Codebase;

/**
 * Two-or-more TypeScript functions with one SHAPE but not one body — the same control flow, differing
 * only in local names or string/number literals (a type-2 clone): each does the same thing to a
 * different endpoint or key, and wants to be one function with a parameter. Members with a
 * byte-identical twin belong to {@see DuplicateFunctionDetector}. A constructor, a body of one
 * statement and a lookup table written as code are left out: none has a skeleton to parameterise. The twin of the backend's
 * {@see \JesseGall\CodeCommandments\Detectors\Backend\NearDuplicateFunctionDetector}.
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

    public function find(Codebase $components): array
    {
        $candidates = $components
            ->whereFunction()
            ->where(static fn (NodeMatch $match): bool => $match->bodyNodeCount() >= self::MIN_BODY_WEIGHT)
            ->reject(static fn (NodeMatch $match): bool => $match->isConstructorDeclaration())
            ->reject(static fn (NodeMatch $match): bool => $match->isSoleReturnExpression())
            ->reject(static fn (NodeMatch $match): bool => $match->isSoleExpressionStatement())
            ->reject(static fn (NodeMatch $match): bool => $match->isLiteralLookup())
            ->get();

        $copies = array_count_values(array_map(static fn (NodeMatch $match): string => $match->bodyHash(), $candidates));
        $findings = [];

        foreach ($this->recurringBuckets($candidates, $components) as $shape) {
            // A member with a byte-identical twin is the exact detector's finding, never this one's.
            foreach ($shape as $match) {
                if ($copies[$match->bodyHash()] === 1) {
                    $findings[] = $match;
                }
            }
        }

        return $findings;
    }
}
