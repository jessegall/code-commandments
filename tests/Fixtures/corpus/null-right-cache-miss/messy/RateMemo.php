<?php

namespace App\OptionCorpus\NullRightCacheMiss\messy;

use Illuminate\Contracts\Cache\Repository as Cache;
use JesseGall\PhpTypes\Option;

/** Reads cached FX rates, wrapping the cache miss in an Option for no reason. */
final readonly class RateMemo
{
    public function __construct(
        private Cache $cache,
    ) {}

    /** Cached rate wrapped in an Option — built only to be torn straight back open. */
    public function lookup(string $pair): Option
    {
        // The cache already returns ?ExchangeRate; wrapping it adds a box no caller keeps.
        return Option::fromNullable($this->cache->get("fx:{$pair}"));
    }

    public function store(ExchangeRate $rate, int $ttlSeconds): void
    {
        $this->cache->put("fx:{$rate->pair}", $rate, $ttlSeconds);
    }
}
