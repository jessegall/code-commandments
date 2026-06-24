<?php

namespace App\Orders;

use Illuminate\Support\Collection;

/**
 * A customer's order: its current status and the line items it holds.
 */
final readonly class Order
{
    /**
     * @param Collection<int, LineItem> $lineItems
     */
    public function __construct(
        public string $id,
        public string $customerId,
        public OrderStatus $status,
        public Collection $lineItems,
    ) {}
}
