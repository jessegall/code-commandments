<?php

namespace Shop\Lockers;

use RuntimeException;

/**
 * A parcel was sent to a locker that is not taking parcels.
 */
final class LockerOffline extends RuntimeException
{
    public static function at(string $site): self
    {
        return new self("The locker at {$site} is not accepting parcels.");
    }
}
