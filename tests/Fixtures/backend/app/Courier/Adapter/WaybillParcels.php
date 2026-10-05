<?php

namespace Shop\Courier\Adapter;

use JesseGall\CodeCommandments\Sins\Backend\DerivedArgument;
use JesseGall\CodeCommandments\Testing\Righteous;
use Shop\Courier\Manifest\CourierParcel;
use Shop\Dispatch\Waybill;

/**
 * Maps the shop's waybills onto the courier's parcels, the one place that knows both.
 */
final class WaybillParcels
{
    /**
     * Righteous twin: the courier's manifest and the shop's dispatch reference each other in neither direction, so
     * handing CourierParcel the waybill would couple them; reading the waybill here is the adapter's whole job.
     */
    #[Righteous(DerivedArgument::class)]
    public function parcelFor(Waybill $waybill): CourierParcel
    {
        return new CourierParcel($waybill->trackingCode(), $waybill->weightGrams(), $waybill->isHeavy());
    }
}
