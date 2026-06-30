<?php

namespace Shop\Data;

use Spatie\LaravelData\Data;

/**
 * One line on an order.
 */
final class OrderLineData extends Data
{
    public function __construct(
        public readonly int $productId,
        public readonly int $quantity,
        public readonly int $priceCents,
    ) {}
}
