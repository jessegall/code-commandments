<?php

declare(strict_types=1);

namespace Shop\Cart;

/**
 * ADDED CLASS — a Null Object. "No discount" is a real, nameable behaviour:
 * identity on the amount and a fixed label. Centralising it here lets
 * activeDiscount() be total, so callers stop branching on null.
 */
final class NoDiscount implements Discount
{
    public function applyTo(int $amountCents): int
    {
        return $amountCents;
    }

    public function label(): string
    {
        return 'No discount';
    }
}
