<?php

namespace App\OptionCorpus\NullRightCacheMiss\golden;

use Illuminate\Contracts\Cache\Repository as Cache;

/** Reads cached FX rates; null IS the cache-miss signal. */
final readonly class RateMemo
{
    public function __construct(
        private Cache $cache,
    ) {}

    /** Cached rate for the pair, or null when the cache holds nothing (a miss). */
    public function lookup(string $pair): ?ExchangeRate
    {
        return $this->cache->get("fx:{$pair}");
    }

    public function store(ExchangeRate $rate, int $ttlSeconds): void
    {
        $this->cache->put("fx:{$rate->pair}", $rate, $ttlSeconds);
    }
}
