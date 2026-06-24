<?php

namespace App\FeatureEnvy\CartDiscount;

/**
 * A customer placing the cart, owning their own loyalty discount rate.
 */
final readonly class Customer
{
    public function __construct(
        public string $id,
        public string $name,
        public CustomerTier $tier,
    ) {}

    public function discountPercent(): int
    {
        return $this->tier->discountPercent();
    }
}
