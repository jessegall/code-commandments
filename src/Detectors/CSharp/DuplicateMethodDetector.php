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
use JesseGall\CodeCommandments\Sins\CSharp\DuplicateMethod;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * Two-or-more C# members with one body — methods, accessors and local functions alike —
 * compared by a formatting-blind fingerprint of the body alone, so the copy that was renamed is caught
 * with the one that was not. The twin of the Python and TypeScript duplicate-function rules; bodies
 * below a size floor are alike by coincidence, and two classes' constructors each set their own state,
 * so neither is compared.
 */
final class DuplicateMethodDetector implements Detector, RecurrenceDetector
{
    use BucketsByGroupKey;

    /**
     * Minimum body weight — statements and expressions — for a member to be worth comparing.
     */
    private const int MIN_BODY_WEIGHT = 12;

    public function sin(): Sin
    {
        return new DuplicateMethod();
    }

    public function groupKey(Located $finding, BaseCodebase $codebase): ?string
    {
        return $finding instanceof NodeMatch && $finding->bodyHash() !== '' ? $finding->bodyHash() : null;
    }

    public function find(Codebase $codebase): array
    {
        $candidates = $codebase
            ->whereFunction()
            ->where(static fn (NodeMatch $match): bool => $match->bodyNodeCount() >= self::MIN_BODY_WEIGHT)
            ->reject(static fn (NodeMatch $match): bool => $match->isConstructorDeclaration())
            ->get();

        return array_merge(...$this->recurringBuckets($candidates, $codebase));
    }
}
