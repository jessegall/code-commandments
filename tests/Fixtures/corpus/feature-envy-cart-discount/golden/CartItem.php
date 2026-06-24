<?php

namespace App\FeatureEnvy\CartDiscount;

/**
 * A single line in the cart: a product, a quantity, a unit price.
 */
final readonly class CartItem
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
