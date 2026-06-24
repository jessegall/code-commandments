<?php

declare(strict_types=1);

namespace App\Orders;

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

    public function add(Money $other): self
    {
        return new self($this->cents + $other->cents, $this->currency);
    }

    public function multiply(int $factor): self
    {
        return new self($this->cents * $factor, $this->currency);
    }
}
