<?php

namespace Shop\Lockers;

use Shop\Models\Depot;
use Shop\Models\DepotLocker;

/**
 * Finds a locker among the lockers of the depot it serves.
 */
final class DepotLockerFinder implements LockerFinder
{
    use ServesDepot;

    public function __construct(Depot $depot)
    {
        $this->depot = $depot;
    }

    public function find(int $locker): ?DepotLocker
    {
        return $this->depot->lockers()->find($locker);
    }
}
