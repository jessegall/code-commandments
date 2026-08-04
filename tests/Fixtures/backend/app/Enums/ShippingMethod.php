<?php

namespace Shop\Enums;

use JesseGall\CodeCommandments\Sins\Backend\MemberAfterMethod;
use JesseGall\CodeCommandments\Sins\Backend\NamespaceCycle;
use JesseGall\CodeCommandments\Testing\Fixed;
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

/**
 * The FIX: the trailing `case Pickup` hoisted up beside the cases it belongs with, so the head of the
 * enum is the whole inventory and the behaviour sits below it.
 */
#[Fixed(MemberAfterMethod::class)]
enum CollectionMethod: string
{
    case Standard = 'standard';
    case Express = 'express';
    case Pickup = 'pickup';

    public function surchargeCents(): int
    {
        return match ($this) {
            self::Standard => 0,
            self::Express => 750,
            self::Pickup => 100,
        };
    }
}
