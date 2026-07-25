<?php

namespace Shop\Claims;

use Shop\Warranty\WarrantyPolicy;

/**
 * One warranty claim raised against a policy.
 */
final class Claim
{
    public function __construct(public readonly int $coveredMonths) {}

    public function policy(): WarrantyPolicy
    {
        return new WarrantyPolicy($this->coveredMonths);
    }
}
