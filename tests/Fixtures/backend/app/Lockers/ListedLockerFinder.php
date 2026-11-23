<?php

namespace Shop\Lockers;

use Shop\Models\DepotLocker;

/**
 * Finds a locker among a list handed in, for a depot whose lockers were loaded once.
 */
final class ListedLockerFinder implements LockerFinder
{
    /**
     * @param  array<int, DepotLocker>  $lockers
     */
    public function __construct(private readonly array $lockers) {}

    public function find(int $locker): ?DepotLocker
    {
        return $this->lockers[$locker] ?? null;
    }
}
