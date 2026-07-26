<?php

namespace Shop\Enums;

use JesseGall\CodeCommandments\Sins\Backend\MemberAfterMethod;
use JesseGall\CodeCommandments\Sins\Backend\NamespaceCycle;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Shipping\ShippingRateRegistry;

#[Sinful(MemberAfterMethod::class)]
enum ShippingMethod: string
{
    case Standard = 'standard';
    case Express = 'express';

    /**
     * Shipping reaches for this enum all over; this is the ONE place the enum reaches back, and it
     * welds the two namespaces into a single unit — neither can now be lifted out alone.
     */
    #[Sinful(NamespaceCycle::class)]
    public function rateCents(int $weightGrams): int
    {
        // An enum case can never be built by the container, so resolving the
        // rate registry through app() is the only option here.
        return app(ShippingRateRegistry::class)->for($this)->quote($weightGrams);
    }

    case Pickup = 'pickup';
}
