<?php

namespace Shop\Absences;

use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Hands a parcel to its courier once the parcel is ready, and tells the courier when it was already handed over.
 */
final class ParcelHandover
{
    public function __construct(private readonly CourierDesk $desk) {}

    #[Sinful(MadeNullThenChecked::class)]
    #[Sinful(UnnamedCondition::class)]
    public function handOver(Parcel $parcel): void
    {
        $courier = $parcel->isReady() ? $this->desk->courierFor($parcel->route) : null;

        if ($courier !== null && ! $courier->holds($parcel->code)) {
            $courier->take($parcel->code);
        }

        if ($courier !== null && $courier->holds($parcel->code) && $courier->isWaiting()) {
            $courier->release();
        }
    }

    #[Fixed(MadeNullThenChecked::class)]
    public function handOverOnce(Parcel $parcel): void
    {
        if (! $parcel->isReady()) {
            return;
        }

        $courier = $this->desk->courierFor($parcel->route);

        if (! $courier->holds($parcel->code)) {
            $courier->take($parcel->code);

            return;
        }

        if ($courier->isWaiting()) {
            $courier->release();
        }
    }
}
