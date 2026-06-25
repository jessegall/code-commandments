<?php

namespace App\Inventory;

/**
 * The typed, validated intent to reserve a quantity of a SKU.
 */
final readonly class AllocationRequest
{
    public function __construct(
        public Sku $sku,
        public int $quantity,
    ) {}
}
