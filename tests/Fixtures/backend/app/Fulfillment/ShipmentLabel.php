<?php

namespace Shop\Fulfillment;

use JesseGall\CodeCommandments\Sins\Backend\PhantomNullable;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * The shipment label — carries the delivery address on to the carrier manifest.
 */
#[Sinful(PhantomNullable::class)]
final class ShipmentLabel
{
    public ?ShippingAddress $deliverTo = null;

    public function record(Manifest $manifest): void
    {
        $manifest->deliverTo = $this->deliverTo;
    }
}
