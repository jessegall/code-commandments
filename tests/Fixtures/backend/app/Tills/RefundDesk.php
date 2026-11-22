<?php

namespace Shop\Tills;

/**
 * Takes a sale back when the sale plays the refundable role, and refunds nothing otherwise.
 */
final class RefundDesk
{
    public function refund(object $sale): int
    {
        return $sale instanceof Refundable ? $sale->refundCents() : 0;
    }
}
