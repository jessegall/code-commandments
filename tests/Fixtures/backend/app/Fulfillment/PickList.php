<?php

namespace Shop\Fulfillment;

use JesseGall\CodeCommandments\Sins\Backend\PhantomNullable;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * The warehouse pick list — carries the delivery address forward to the packing slip.
 */
#[Sinful(PhantomNullable::class)]
final class PickList
{
    public ?ShippingAddress $deliverTo = null;

    public function forward(PackingSlip $slip): void
    {
        $slip->deliverTo = $this->deliverTo;
    }
}
