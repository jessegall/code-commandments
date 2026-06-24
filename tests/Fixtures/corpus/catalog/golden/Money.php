<?php

namespace App\Catalog;

/**
 * An exact amount of money held in minor units (cents) with its currency.
 */
final readonly class Money
{
    public function __construct(
        public int $amountCents,
        public string $currency,
    ) {}

    public function add(Money $other): self
    {
        return new self($this->amountCents + $other->amountCents, $this->currency);
    }

    public function multipliedBy(int $quantity): self
    {
        return new self($this->amountCents * $quantity, $this->currency);
    }

    public function isFree(): bool
    {
        return $this->amountCents === 0;
    }
}
