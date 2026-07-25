<?php

namespace Shop\Claims;

use Shop\Warranty\WarrantyPolicy;

/**
 * Where a customer's claim is assessed against the warranty terms.
 */
final class ClaimDesk
{
    public function assess(Claim $claim, WarrantyPolicy $policy): string
    {
        return $claim->coveredMonths <= $policy->months ? 'covered' : 'expired';
    }
}
