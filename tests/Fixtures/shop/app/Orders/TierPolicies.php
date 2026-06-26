<?php

namespace Shop\Orders;

final class TierPolicies
{
    public function for(string $tier): TierPolicy
    {
        return new TierPolicy();
    }
}
