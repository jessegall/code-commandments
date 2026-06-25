<?php

namespace App\Inventory;

/**
 * How many units of a SKU a warehouse holds available to reserve.
 */
final readonly class StockLevel
{
    public function __construct(
        public Sku $sku,
        public int $available,
    ) {}

    public function canCover(int $quantity): bool
    {
        return $this->available >= $quantity;
    }

    public function reduceBy(int $quantity): self
    {
        return new self($this->sku, $this->available - $quantity);
    }
}
