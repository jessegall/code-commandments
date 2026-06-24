<?php

namespace App\OptionCorpus\NullRightCacheMiss\messy;

/** Renders a "live or stale" badge after unwrapping the Option back to a nullable. */
final readonly class PriceTag
{
    public function __construct(
        private RateMemo $memo,
    ) {}

    public function freshnessBadge(string $pair): string
    {
        // Call site 2: the Option is unwrapped straight back to ?ExchangeRate with
        // getOrElse(null) — round-tripping value -> Option -> value for nothing.
        $rate = $this->memo->lookup($pair)->getOrElse(null);

        return $rate?->asOf !== null ? 'live' : 'stale';
    }
}
