<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\CSharp;

use JesseGall\CodeCommandments\Codebase as BaseCodebase;
use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\NodeMatch;
use JesseGall\CodeCommandments\CSharp\Detector;
use JesseGall\CodeCommandments\Detectors\BucketsByGroupKey;
use JesseGall\CodeCommandments\Detectors\RecurrenceDetector;
use JesseGall\CodeCommandments\Located;
use JesseGall\CodeCommandments\Sins\CSharp\ConvertedArgument;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\WholeTree;

/**
 * A scalar parameter declared in the wrong currency — the twin of the backend's and Python's
 * converted-argument rules. The evidence is the same conversion filling the same parameter call after call:
 * the method knows the conversion and makes every caller keep it.
 */
final class ConvertedArgumentDetector implements Detector, RecurrenceDetector, WholeTree
{
    use BucketsByGroupKey;

    /**
     * The conversion must be what that parameter USUALLY gets. On two of nineteen sites it is those callers' own
     * business; at half or more it is the parameter's real currency.
     */
    private const float DOMINANT = 0.5;

    public function sin(): Sin
    {
        return new ConvertedArgument();
    }

    /**
     * `method#position=conversion` of the parameter a call converts for — null for a call that hands no scalar
     * parameter a conversion.
     */
    public function groupKey(Located $finding, BaseCodebase $codebase): ?string
    {
        if (! $finding instanceof NodeMatch || ! $codebase instanceof Codebase || ! $codebase->reachesOwnSignature($finding->node)) {
            return null;
        }

        foreach ($finding->node->scalarConversions() as $position => $conversion) {
            return "{$finding->node->target?->symbol()}#{$position}={$conversion}";
        }

        return null;
    }

    public function find(Codebase $codebase): array
    {
        $calls = $codebase->whereCall()->get();
        $supplied = $this->supplied($calls);
        $buckets = $this->recurringBuckets($calls, $codebase);
        $dominant = array_filter($buckets, fn (array $bucket): bool => count($bucket) / $supplied[explode('=', (string) $this->groupKey($bucket[0], $codebase))[0]] >= self::DOMINANT);

        return array_merge([], ...array_values($dominant));
    }

    /**
     * How many calls reach each method with each argument — the denominator behind {@see DOMINANT}.
     *
     * @param  list<NodeMatch>  $calls
     * @return array<string, int>
     */
    private function supplied(array $calls): array
    {
        $counts = [];

        foreach ($calls as $call) {
            foreach (array_keys($call->node->arguments()) as $position) {
                $slot = $call->node->target?->symbol() . "#{$position}";
                $counts[$slot] = ($counts[$slot] ?? 0) + 1;
            }
        }

        return $counts;
    }
}
