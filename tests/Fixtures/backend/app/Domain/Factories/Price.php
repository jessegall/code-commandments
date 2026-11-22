<?php

namespace Shop\Domain\Factories;

final class Price
{
    private function __construct(private readonly int $cents, private readonly string $currency)
    {
    }

    public static function of(int $cents, string $currency): self
    {
        return new self(max(0, $cents), strtoupper($currency));
    }

    public function cents(): int
    {
        return $this->cents;
    }

    public function currency(): string
    {
        return $this->currency;
    }
}
