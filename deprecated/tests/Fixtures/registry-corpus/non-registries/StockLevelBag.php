<?php

namespace App\RegistryCorpus;

use Illuminate\Support\Collection;

/**
 * REGISTRY: no — it is an immutable value object wrapping a fixed array of stock
 * levels with typed read accessors; nothing is keyed-in/looked-up, it just exposes data.
 */
final class StockLevelBag
{
    /** @param array<int, int> $levels warehouse id => units on hand */
    public function __construct(
        private readonly array $levels,
    ) {}

    public function total(): int
    {
        return array_sum($this->levels);
    }

    public function isEmpty(): bool
    {
        return $this->total() === 0;
    }

    public function lowest(): int
    {
        return empty($this->levels) ? 0 : min($this->levels);
    }

    /** @return Collection<int, int> */
    public function toCollection(): Collection
    {
        return collect($this->levels);
    }
}
