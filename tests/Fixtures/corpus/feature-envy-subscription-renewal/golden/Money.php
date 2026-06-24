<?php

namespace App\FeatureEnvy\SubscriptionRenewal;

/**
 * An amount of money held as integer cents in a single currency.
 */
final readonly class Money
{
    public function __construct(
        public int $cents,
        public string $currency = 'USD',
    ) {}

    public function times(int $factor): self
    {
        return new self($this->cents * $factor, $this->currency);
    }

    public function format(): string
    {
        return sprintf('%s %.2f', $this->currency, $this->cents / 100);
    }
}
