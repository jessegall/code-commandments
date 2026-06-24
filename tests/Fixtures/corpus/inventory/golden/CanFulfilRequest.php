<?php

declare(strict_types=1);

namespace App\Inventory;

/**
 * Matches a warehouse that stocks the requested SKU and can cover the quantity.
 */
final class CanFulfilRequest implements WarehousePredicate
{
    public static function make(): self
    {
        return new self();
    }

    public function matches(Warehouse $warehouse, AllocationRequest $request): bool
    {
        return $warehouse->sku()->equals($request->sku)
            && $warehouse->canFulfil($request->quantity);
    }
}
