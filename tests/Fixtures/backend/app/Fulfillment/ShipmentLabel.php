<?php

namespace Shop\Fulfillment;

/**
 * The shipment label — carries the delivery address on to the carrier manifest.
 */
final class ShipmentLabel
{
    public ?ShippingAddress $deliverTo = null;

    public function record(Manifest $manifest): void
    {
        $manifest->deliverTo = $this->deliverTo;
    }
}
