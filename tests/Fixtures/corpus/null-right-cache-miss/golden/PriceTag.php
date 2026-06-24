<?php

namespace App\OptionCorpus\NullRightCacheMiss\golden;

/** Renders a "live or stale" badge straight off the nullable miss. */
final readonly class PriceTag
{
    public function __construct(
        private RateMemo $memo,
    ) {}

    public function freshnessBadge(string $pair): string
    {
        // Call site 2: a single `?->` chain; absence collapses to "stale".
        return $this->memo->lookup($pair)?->asOf !== null ? 'live' : 'stale';
    }
}
