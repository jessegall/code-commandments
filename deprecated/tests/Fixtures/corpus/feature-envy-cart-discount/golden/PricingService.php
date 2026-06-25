<?php

namespace App\FeatureEnvy\CartDiscount;

/**
 * Computes the discount payable on a cart by composing what the cart owns.
 */
final class PricingService
{
    public function discountFor(Cart $cart): Money
    {
        return $cart->subtotal()->percentage($cart->discountPercent());
    }

    public function payable(Cart $cart): Money
    {
        return $cart->subtotal()->subtract($this->discountFor($cart));
    }
}
