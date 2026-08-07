<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

/**
 * What one detector cost on a profiling run — the row {@see Benchmark} sorts and prints. Shards is
 * null for a rule that cannot be divided: one indivisible task pinning one core.
 */
final readonly class DetectorProfile
{
    public function __construct(
        public string $name,
        public float $seconds,
        public int $matches,
        public int $bytes,
        public ?int $shards = null,
    ) {}

    /**
     * This row's share of the whole detection pass, as a percentage.
     */
    public function shareOf(float $total): float
    {
        return $total > 0.0 ? $this->seconds / $total * 100 : 0.0;
    }

    /**
     * The shard count as the table shows it — a rule that cannot be divided prints a dot rather
     * than a number it does not have.
     */
    public function shardsColumn(): string
    {
        return $this->shards === null ? '·' : (string) $this->shards;
    }
}
