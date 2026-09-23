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
use JesseGall\CodeCommandments\Sins\Python\DuplicateFunction;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * Two-or-more Python functions with one body — module functions and methods alike — compared by a
 * formatting-blind fingerprint of the body alone, docstrings aside, so the copy that was renamed is
 * caught with the one that was not. The twin of the TypeScript
 * {@see \JesseGall\CodeCommandments\Detectors\Frontend\TypeScript\DuplicateFunctionDetector}; bodies
 * below a size floor are alike by coincidence, and two classes' `__init__`s each set their own state, so
 * neither is compared.
 */
final class DuplicateFunctionDetector implements Detector, RecurrenceDetector
{
    use BucketsByGroupKey;

    /**
     * Minimum body weight — statements and expressions — for a function to be worth comparing.
     */
    private const int MIN_BODY_WEIGHT = 12;

    public function sin(): Sin
    {
        return new DuplicateFunction();
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
