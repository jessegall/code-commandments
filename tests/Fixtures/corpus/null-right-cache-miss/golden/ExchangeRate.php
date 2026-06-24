<?php

namespace App\OptionCorpus\NullRightCacheMiss\golden;

/** An immutable FX rate snapshot. */
final readonly class ExchangeRate
{
    public function __construct(
        public string $pair,
        public float $rate,
        public int $asOf,
    ) {}
}
