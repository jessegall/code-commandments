<?php

namespace App\OptionCorpus\NullRightCacheMiss\messy;

/** The slow source of truth behind the memo. */
final readonly class UpstreamFeed
{
    public function quote(string $pair): ExchangeRate
    {
        return new ExchangeRate($pair, 1.0, time());
    }
}
