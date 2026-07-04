<?php

namespace Shop\Fulfillment;

use JesseGall\CodeCommandments\Sins\Backend\PhantomNullable;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * An order entering fulfillment. `$shipTo` is typed `?ShippingAddress`, but staging copies it onto the
 * pick list, which copies it onto the packing slip, onto the label, onto the manifest — five records
 * across five files — where it is finally read as present, and no step ever guards the null.
 */
#[Sinful(PhantomNullable::class)]
final class Order
{
    public function __construct(
        public readonly ?ShippingAddress $shipTo,
        public readonly string $reference,
    ) {}

    public function stage(PickList $pickList): void
    {
        $pickList->deliverTo = $this->shipTo;
    }
}
