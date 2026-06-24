<?php

namespace App\Orders;

/**
 * Computes the tax owed on a subtotal at a fixed basis-point rate.
 */
final class TaxCalculator
{
    public function __construct(
        private readonly int $rateBasisPoints,
    ) {}

    public function on(Money $subtotal): Money
    {
        return new Money(
            intdiv($subtotal->cents * $this->rateBasisPoints, 10_000),
            $subtotal->currency,
        );
    }
}
