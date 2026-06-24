<?php

declare(strict_types=1);

namespace App\Inventory;

/**
 * A composable test deciding whether a warehouse may serve an allocation request.
 */
interface WarehousePredicate
{
    public function matches(Warehouse $warehouse, AllocationRequest $request): bool;
}
