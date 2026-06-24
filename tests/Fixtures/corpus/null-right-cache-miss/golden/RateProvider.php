<?php

namespace App\OptionCorpus\NullRightCacheMiss\golden;

/** Resolves an FX rate, recomputing from the upstream feed on a cache miss. */
final readonly class RateProvider
{
    public function __construct(
        private RateMemo $memo,
        private UpstreamFeed $feed,
    ) {}

    public function rateFor(string $pair): ExchangeRate
    {
        // Call site 1: null miss -> recompute, store, return. One clean `?? recompute`.
        return $this->memo->lookup($pair) ?? $this->refresh($pair);
    }

    private function refresh(string $pair): ExchangeRate
    {
        $rate = $this->feed->quote($pair);
        $this->memo->store($rate, ttlSeconds: 300);

        return $rate;
    }
}
