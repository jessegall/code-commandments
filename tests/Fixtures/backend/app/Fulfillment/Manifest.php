<?php

namespace Shop\Fulfillment;

use JesseGall\CodeCommandments\Sins\Backend\PhantomNullable;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * The carrier manifest — the last record, where the delivery address is read as present to print the
 * routing line, five records on from the order.
 */
#[Sinful(PhantomNullable::class)]
final class Manifest
{
    public ?ShippingAddress $deliverTo = null;

    public function routingLine(): string
    {
        return $this->deliverTo->postalCode();
    }
}
