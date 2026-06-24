<?php

namespace App\OptionCorpus\NullRightCacheMiss\messy;

/** Resolves an FX rate, recomputing from the upstream feed on a cache miss. */
final readonly class RateProvider
{
    public function __construct(
        private RateMemo $memo,
        private UpstreamFeed $feed,
    ) {}

    public function rateFor(string $pair): ExchangeRate
    {
        // Call site 1: build an Option then immediately interrogate + unwrap it.
        // Pure ceremony — `?? $this->refresh($pair)` said the same thing on a nullable.
        $cached = $this->memo->lookup($pair);

        if ($cached->isSome()) {
            return $cached->getOrThrow();
        }

        return $this->refresh($pair);
    }

    private function refresh(string $pair): ExchangeRate
    {
        $rate = $this->feed->quote($pair);
        $this->memo->store($rate, ttlSeconds: 300);

        return $rate;
    }
}
