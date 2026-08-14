<?php

namespace Shop\Dispatch;

use JesseGall\CodeCommandments\Sins\Backend\DerivedArgument;

use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Spells one waybill out into three arguments, so the courier is handed the same subject three times
 * over in pieces.
 */
final class ParcelDispatch
{
    public function __construct(private readonly CourierBooking $courier) {}

    #[Sinful(DerivedArgument::class)]
    public function dispatch(Waybill $waybill): string
    {
        return $this->courier->book(
            $waybill->trackingCode(),
            $waybill->weightGrams(),
            $waybill->isHeavy(),
        );
    }

    /**
     * The FIX: hand over the waybill and let the courier read what it needs off it.
     */
    #[Fixed(DerivedArgument::class)]
    public function dispatchWhole(Waybill $waybill): string
    {
        return $this->courier->bookWaybill($waybill);
    }

    /**
     * Righteous twin: two projections, but of DIFFERENT waybills. There is no single subject to hand
     * over in place of both, so nothing can move.
     */
    #[Righteous(DerivedArgument::class)]
    public function compare(Waybill $mine, Waybill $theirs): string
    {
        return $this->courier->quote($mine->trackingCode(), $theirs->weightGrams());
    }
}
