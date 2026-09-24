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
use JesseGall\CodeCommandments\Sins\CSharp\RepeatedGuard;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * The same compound condition about an object's data asked at two or more sites — the C# twin of the PHP and
 * Python repeated-guard rules. A condition stored in a variable is a value kept, not a question asked.
 */
final class RepeatedGuardDetector implements Detector, RecurrenceDetector
{
    use BucketsByGroupKey;

    public function sin(): Sin
    {
        return new RepeatedGuard();
    }

    public function groupKey(Located $finding, BaseCodebase $codebase): ?string
    {
        return $finding instanceof NodeMatch && $finding->node->isSubstantiveGuard() ? $finding->node->guardFingerprint() : null;
    }

    public function find(Codebase $codebase): array
    {
        $guards = $codebase
            ->whereExpression(static fn (Node $expression): bool => $expression->isSubstantiveGuard())
            ->where(static fn (NodeMatch $match): bool => $match->isOutermostAnd())
            ->reject(static fn (NodeMatch $match): bool => $match->isStoredValue())
            ->get();

        return array_merge([], ...$this->recurringBuckets($guards, $codebase));
    }
}
