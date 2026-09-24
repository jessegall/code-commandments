<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\CSharp;

use JesseGall\CodeCommandments\Codebase as BaseCodebase;
use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\Node;
use JesseGall\CodeCommandments\Cs\NodeMatch;
use JesseGall\CodeCommandments\CSharp\Detector;
use JesseGall\CodeCommandments\Detectors\BucketsByGroupKey;
use JesseGall\CodeCommandments\Detectors\RecurrenceDetector;
use JesseGall\CodeCommandments\Located;
use JesseGall\CodeCommandments\Sins\CSharp\RepeatedTypeGuard;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * The same chain of two or more type checks at two or more sites — the C# twin of the PHP and Python
 * repeated-type-guard rules.
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
        return $finding instanceof NodeMatch && $finding->node->isTypeNarrowingGuard() ? $finding->node->guardFingerprint() : null;
    }

    public function find(Codebase $codebase): array
    {
        $guards = $codebase
            ->whereExpression(static fn (Node $expression): bool => $expression->isTypeNarrowingGuard())
            ->where(static fn (NodeMatch $match): bool => $match->isOutermostAnd())
            ->get();

        return array_merge([], ...$this->recurringBuckets($guards, $codebase));
    }
}
