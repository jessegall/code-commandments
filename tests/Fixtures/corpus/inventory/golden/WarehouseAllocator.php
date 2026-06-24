<?php

namespace App\Inventory;

/**
 * Picks the first registered warehouse whose predicate matches an allocation request.
 */
final class WarehouseAllocator
{
    public function __construct(
        private readonly WarehouseRegistry $warehouses,
        private readonly WarehousePredicate $strategy,
    ) {}

    public function allocate(AllocationRequest $request): ReservationResult
    {
        foreach ($this->warehouses->all() as $warehouse) {
            if ($this->strategy->matches($warehouse, $request)) {
                $warehouse->withdraw($request->quantity);

                return ReservationResult::fulfilledBy($warehouse, $request->quantity);
            }
        }

        return ReservationResult::empty();
    }
}
