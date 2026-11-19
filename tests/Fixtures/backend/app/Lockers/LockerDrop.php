<?php

namespace Shop\Lockers;

use JesseGall\CodeCommandments\Sins\Backend\GuardBesideTransition;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Drops a courier's parcels into the locker of each site on the route.
 */
final class LockerDrop
{
    public function __construct(private readonly array $lockersBySite) {}

    #[Sinful(GuardBesideTransition::class)]
    public function drop(string $site, string $parcelCode): string
    {
        $locker = $this->lockersBySite[$site];
        if ($locker->mode != LockerMode::Accepting) {
            throw LockerOffline::at($site);
        }

        $locker->place($parcelCode);

        return $locker->site;
    }
}
