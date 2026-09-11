<?php

namespace Shop\Orders;

use Shop\Enums\ShippingMethod;

final class TierPolicies
{
    public function for(string $tier): TierPolicy
    {
        return new TierPolicy();
    }

    public function forShipping(ShippingMethod $method): TierPolicy
    {
        return new TierPolicy();
    }
}
