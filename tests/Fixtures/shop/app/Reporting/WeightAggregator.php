<?php

namespace Shop\Reporting;

use JesseGall\CodeCommandments\Detectors\Backend\NearDuplicateFunctionDetector;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Aggregates parcel weights into a histogram. accumulateFrom is the same loop as
 * the pricing and shipping scorers — same shape, different names and constant.
 */
final class WeightAggregator
{
    /** @var list<int> */
    private array $entries = [];

    private string $unit = 'g';

    public function push(int $grams): void
    {
        $this->entries[] = $grams;
    }

    /**
     * @return array<string, int>
     */
    public function histogram(int $bucketSize): array
    {
        $buckets = [];

        foreach ($this->entries as $grams) {
            $key = $this->unit . (intdiv($grams, $bucketSize) * $bucketSize);
            $buckets[$key] = ($buckets[$key] ?? 0) + 1;
        }

        return $buckets;
    }

    #[Sinful(NearDuplicateFunctionDetector::class)]
    public function accumulateFrom(int $start): int
    {
        $total = $start;

        foreach ($this->entries as $row) {
            if ($row > 0) {
                $total += $row * 5;
            }
        }

        return $total;
    }
}
