<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Frontend\TypeScript;

use JesseGall\CodeCommandments\Codebase as BaseCodebase;
use JesseGall\CodeCommandments\Detectors\BucketsByGroupKey;
use JesseGall\CodeCommandments\Detectors\RecurrenceDetector;
use JesseGall\CodeCommandments\Frontend\Detector;
use JesseGall\CodeCommandments\Located;
use JesseGall\CodeCommandments\Sins\Frontend\TypeScript\DuplicateFunction;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Ts\NodeMatch;
use JesseGall\CodeCommandments\Vue\Codebase;

/**
 * Two-or-more TypeScript functions with one body — a `function`, a method and a `const` arrow alike, in a
 * `.ts` module or a component's `<script>` — compared by a formatting-blind fingerprint of the body alone,
 * so the copy that was renamed is caught with the one that was not. The twin of the backend's
 * {@see \JesseGall\CodeCommandments\Detectors\Backend\DuplicateFunctionDetector}; bodies below a size
 * floor are alike by coincidence and are not compared.
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

    public function find(Codebase $components): array
    {
        $candidates = $components
            ->whereFunction()
            ->where(static fn (NodeMatch $match): bool => $match->bodyNodeCount() >= self::MIN_BODY_WEIGHT)
            ->get();

        return array_merge(...$this->recurringBuckets($candidates, $components));
    }
}
