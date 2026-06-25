<?php

namespace Shop\ValueObjects;

final readonly class Sku
{
    private function __construct(public string $value) {}

    public static function of(string $value): self
    {
        return new self(strtoupper(trim($value)));
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
