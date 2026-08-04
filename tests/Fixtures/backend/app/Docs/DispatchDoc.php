<?php

namespace Shop\Docs;

use JesseGall\CodeCommandments\Sins\Backend\DanglingDocReference;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Routes a parcel to a carrier.
 */
final class DispatchDoc
{
    /**
     * Picks the cheapest carrier. Rates come from {@see \Shop\Shipping\RemovedRateBook} — deleted, so the
     * link is stale.
     */
    #[Sinful(DanglingDocReference::class)]
    public function cheapest(string $zone, float $weight): string
    {
        return $weight > 10.0 ? "freight:{$zone}" : "parcel:{$zone}";
    }

    /**
     * Picks the cheapest carrier for a heavy parcel. Rates come from
     * {@see \Shop\Shipping\ShippingRateRegistry} — the class the rate book became, so the reference
     * resolves.
     */
    #[Fixed(DanglingDocReference::class)]
    public function cheapestFreight(string $zone): string
    {
        return "freight:{$zone}";
    }

    public function label(string $carrier): string
    {
        return strtoupper($carrier);
    }
}
