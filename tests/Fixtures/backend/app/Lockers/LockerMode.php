<?php

namespace Shop\Lockers;

/**
 * Whether a pickup locker takes parcels.
 */
enum LockerMode: string
{
    case Accepting = 'accepting';
    case Maintenance = 'maintenance';
}
