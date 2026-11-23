<?php

/**
 * This file is part of the Shop fixture, read by every rule and never run.
 */

declare(strict_types=1);

namespace Shop\Shipping;

use JesseGall\CodeCommandments\Sins\Backend\StackedDocblock;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * The parcels a depot has booked in, read by their tracking code.
 */
interface ParcelLedger
{
    /**
     * The weight booked for the parcel, in grams.
     */

    /**
     * Whether the parcel has left the depot.
     */
    #[Sinful(StackedDocblock::class)]
    public function hasLeft(string $trackingCode): bool;

    /**
     * The depot the parcel waits in.
     */
    public function depotOf(string $trackingCode): string;
}
