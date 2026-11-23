<?php

namespace Shop\Lockers;

use Shop\Models\DepotLocker;

/**
 * Finds a locker by its number.
 */
interface LockerFinder
{
    public function find(int $locker): ?DepotLocker;
}
