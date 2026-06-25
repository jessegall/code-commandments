<?php

namespace App\Inventory;

/**
 * A stock-keeping unit — the immutable identity of a sellable product.
 */
final readonly class Sku
{
    public function __construct(
        public string $code,
    ) {}

    public function equals(Sku $other): bool
    {
        return $this->code === $other->code;
    }
}
