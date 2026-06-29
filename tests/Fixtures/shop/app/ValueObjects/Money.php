<?php

namespace Shop\ValueObjects;

final readonly class Money
{
    private function __construct(
        public int $cents,
        public string $currency = 'EUR',
    ) {}

    public static function ofCents(int $cents, string $currency = 'EUR'): self
    {
        return new self($cents, $currency);
    }

    public function add(self $other): self
    {
        return new self($this->cents + $other->cents, $this->currency);
    }

    public function equals(self $other): bool
    {
        return $this->cents === $other->cents && $this->currency === $other->currency;
    }
}
