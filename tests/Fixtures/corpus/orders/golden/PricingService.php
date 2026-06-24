<?php

declare(strict_types=1);

namespace App\Orders;

/**
 * Computes the total payable for an order, applying tax to the line subtotal.
 */
final class PricingService
{
    public function __construct(
        private readonly TaxCalculator $tax,
    ) {}

    public function total(Order $order, string $currency): Money
    {
        $subtotal = $order->lineItems->reduce(
            static fn (Money $carry, LineItem $item): Money => $carry->add($item->subtotal()),
            Money::zero($currency),
        );

        return $subtotal->add($this->tax->on($subtotal));
    }
}
