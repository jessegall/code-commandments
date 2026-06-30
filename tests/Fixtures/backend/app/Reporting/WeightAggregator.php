<?php

namespace Shop\Reporting;

use JesseGall\CodeCommandments\Sins\Backend\NearDuplicateFunction;

use JesseGall\CodeCommandments\Testing\Righteous;
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

    #[Sinful(NearDuplicateFunction::class)]
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

    /**
     * The duplicated scorers collapsed into one parameterised pass — the per-entry
     * weight is an argument, so there is no rhyming twin to extract.
     */
    #[Righteous(NearDuplicateFunction::class)]
    public function scoreFrom(int $start, int $weight): int
    {
        return array_reduce(
            array_filter($this->entries, static fn (int $row): bool => $row > 0),
            static fn (int $total, int $row): int => $total + $row * $weight,
            $start,
        );
    }
}
