<?php

namespace App\FeatureEnvy\CartDiscount;

/**
 * An amount of money held as an integer of minor units (cents).
 */
final readonly class Money
{
    public function __construct(
        public int $cents,
        public string $currency,
    ) {}

    public static function zero(string $currency): self
    {
        return new self(0, $currency);
    }
}
