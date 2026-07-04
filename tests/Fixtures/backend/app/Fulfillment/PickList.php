<?php

namespace Shop\Fulfillment;

/**
 * The warehouse pick list — carries the delivery address forward to the packing slip.
 */
final class PickList
{
    public ?ShippingAddress $deliverTo = null;

    public function forward(PackingSlip $slip): void
    {
        $slip->deliverTo = $this->deliverTo;
    }
}
