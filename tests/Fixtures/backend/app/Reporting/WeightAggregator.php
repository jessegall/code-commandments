<?php

namespace Shop\Reporting;

use JesseGall\CodeCommandments\Sins\Backend\NarratedCommand;
use JesseGall\CodeCommandments\Sins\Backend\RedundantArrowReturnType;
use JesseGall\CodeCommandments\Sins\Backend\NearDuplicateFunction;
use JesseGall\CodeCommandments\Sins\Backend\RestatedComment;

use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Aggregates parcel weights into a histogram. accumulateFrom is the same loop as
 * the pricing and shipping scorers — same shape, different names and constant.
 */
#[Sinful(RedundantArrowReturnType::class)]
final class WeightAggregator
{
    /**
     * @var list<int>
     */
    private array $entries = [];

    private string $unit = 'g';

    /**
     * The fluent form of the same mistake: chainable, still an order.
     */
    #[Sinful(NarratedCommand::class)]
    public function clears(): static
    {
        $this->entries = [];

        return $this;
    }

    /**
     * The FIX is the NAME: drop the -s. Still fluent, still the same body — but `$weights->clear()`
     * is the order the call site is actually giving, instead of a description of one.
     */
    #[Fixed(NarratedCommand::class)]
    public function clear(): static
    {
        $this->entries = [];

        return $this;
    }

    /**
     * A literal proves its own type.
     */
    public function unit(): callable
    {
        return fn (): string => 'g';
    }

    public function push(int $grams): void
    {
        $this->entries[] = $grams;
    }

    /**
     * @return array<string, int>
     */
    #[Sinful(RestatedComment::class)]
    public function histogram(int $bucketSize): array
    {
        $buckets = [];

        // loop over the entries
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
    #[Fixed(NearDuplicateFunction::class)]
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
