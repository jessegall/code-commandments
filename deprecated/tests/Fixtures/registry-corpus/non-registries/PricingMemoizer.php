<?php

namespace App\RegistryCorpus;

use Illuminate\Support\Collection;

/**
 * REGISTRY: no — the private $cache is internal memoization (compute-once optimization),
 * not a public keyed store you put into and look up from; only priceFor() is public.
 */
class PricingMemoizer
{
    /** @var array<string, int> */
    private array $cache = [];

    public function __construct(
        private readonly PriceCalculator $calculator,
    ) {}

    public function priceFor(string $sku): int
    {
        return $this->cache[$sku] ??= $this->calculator->compute($sku);
    }

    /** @param Collection<int, string> $skus */
    public function priceAll(Collection $skus): Collection
    {
        return $skus->mapWithKeys(fn (string $sku) => [$sku => $this->priceFor($sku)]);
    }

    public function forget(): void
    {
        $this->cache = [];
    }
}
