<?php

namespace App\Inventory;

/**
 * Reserves stock for a request by delegating to the warehouse allocation strategy.
 */
final class ReservationService
{
    public function __construct(
        private readonly WarehouseAllocator $allocator,
    ) {}

    public function reserve(AllocationRequest $request): ReservationResult
    {
        return $this->allocator->allocate($request);
    }
}
