<?php

declare(strict_types=1);

namespace App\Orders;

/**
 * A single priced row on an order: a product, a quantity, a unit price.
 */
final readonly class LineItem
{
    public function __construct(
        public string $sku,
        public int $quantity,
        public Money $unitPrice,
    ) {}

    public function subtotal(): Money
    {
        return $this->unitPrice->multiply($this->quantity);
    }
}
