<?php

namespace App\FeatureEnvy\CartDiscount;

/**
 * Computes a cart discount by reaching through the cart and doing its work.
 */
final class PricingService
{
    public function discountFor(Cart $cart): Money
    {
        $subtotalCents = 0;
        $count = 0;

        foreach ($cart->getItems() as $item) {
            $subtotalCents += $item->getUnitPrice()->cents * $item->getQuantity();
            $count += $item->getQuantity();
        }

        $percent = 0;

        switch ($cart->getCustomer()->getTier()) {
            case CustomerTier::Silver:
                $percent = 5;
                break;
            case CustomerTier::Gold:
                $percent = 10;
                break;
            case CustomerTier::Bronze:
                $percent = 0;
                break;
        }

        if ($count >= 10) {
            $percent += 5;
        }

        $discountCents = (int) ($subtotalCents * $percent / 100);

        return new Money($discountCents, $cart->getCurrency());
    }

    public function payable(Cart $cart): Money
    {
        $subtotalCents = 0;

        foreach ($cart->getItems() as $item) {
            $subtotalCents += $item->getUnitPrice()->cents * $item->getQuantity();
        }

        return new Money($subtotalCents - $this->discountFor($cart)->cents, $cart->getCurrency());
    }
}
