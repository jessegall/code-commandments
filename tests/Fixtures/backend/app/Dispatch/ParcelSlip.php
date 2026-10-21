<?php

namespace Shop\Dispatch;

/**
 * The slip that travels with a parcel. It may name the run that carries it, and may not: a parcel
 * waiting on the floor has no run yet.
 */
final readonly class ParcelSlip
{
    public function __construct(public string $barcode, public ?DepotRun $run = null) {}
}
