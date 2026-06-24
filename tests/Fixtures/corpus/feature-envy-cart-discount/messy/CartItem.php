<?php

namespace App\FeatureEnvy\CartDiscount;

/**
 * A single line in the cart; an anaemic bag of getters.
 */
final readonly class CartItem
{
    public function __construct(
        private string $sku,
        private int $quantity,
        private Money $unitPrice,
    ) {}

    public function getSku(): string
    {
        return $this->sku;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getUnitPrice(): Money
    {
        return $this->unitPrice;
    }
}
