<?php

namespace Shop\Fulfillment;

/**
 * The packing slip — carries the delivery address on to the shipment label.
 */
final class PackingSlip
{
    public ?ShippingAddress $deliverTo = null;

    public function attach(ShipmentLabel $label): void
    {
        $label->deliverTo = $this->deliverTo;
    }
}
