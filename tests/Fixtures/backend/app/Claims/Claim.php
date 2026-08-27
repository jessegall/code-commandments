<?php

namespace Shop\Claims;

use JesseGall\CodeCommandments\Sins\Backend\NamespaceCycle;
use JesseGall\CodeCommandments\Testing\Fixed;
use Shop\Warranty\CoverageClaim;
use Shop\Warranty\WarrantyPolicy;

/**
 * One warranty claim raised against a policy — and the claims desk's side of the cut: it implements
 * the contract Warranty declared, so the only arrow between the two runs Claims → Warranty.
 */
#[Fixed(NamespaceCycle::class)]
final class Claim implements CoverageClaim
{
    public function __construct(public readonly int $coveredMonths) {}

    public function claimedMonths(): int
    {
        return $this->coveredMonths;
    }

    public function policy(): WarrantyPolicy
    {
        return new WarrantyPolicy($this->coveredMonths);
    }
}
