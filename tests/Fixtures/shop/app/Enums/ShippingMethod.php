<?php

namespace Shop\Enums;

use Shop\Shipping\ShippingRateRegistry;

enum ShippingMethod: string
{
    case Standard = 'standard';
    case Express = 'express';
    case Pickup = 'pickup';

    public function rateCents(int $weightGrams): int
    {
        // An enum case can never be built by the container, so resolving the
        // rate registry through app() is the only option here.
        return app(ShippingRateRegistry::class)->for($this)->quote($weightGrams);
    }
}
