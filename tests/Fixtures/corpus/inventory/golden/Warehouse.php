<?php

declare(strict_types=1);

namespace App\Inventory;

/**
 * A physical location that holds stock for a single SKU and can fulfil reservations.
 */
final class Warehouse
{
    public function __construct(
        public readonly string $code,
        private StockLevel $stock,
    ) {}

    public function sku(): Sku
    {
        return $this->stock->sku;
    }

    public function canFulfil(int $quantity): bool
    {
        return $this->stock->canCover($quantity);
    }

    public function withdraw(int $quantity): void
    {
        $this->stock = $this->stock->reduceBy($quantity);
    }
}
