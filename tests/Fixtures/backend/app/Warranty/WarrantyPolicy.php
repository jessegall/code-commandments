<?php

namespace Shop\Warranty;

use JesseGall\CodeCommandments\Sins\Backend\NamespaceCycle;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Claims\Claim;

/**
 * The contract the cycle is cut with: Warranty declares what it needs to know about a claim, and the
 * claims desk implements it. The arrow now runs Claims → Warranty only.
 */
interface CoverageClaim
{
    public function claimedMonths(): int;
}

/**
 * The terms a product is covered under.
 */
final class WarrantyPolicy
{
    public function __construct(public readonly int $months) {}

    /**
     * Claims reads the policy from two places; this is the single arrow pointing back, and it is
     * what stops the warranty terms from being read, tested, or reused without the claims desk.
     */
    #[Sinful(NamespaceCycle::class)]
    public function firstClaim(): Claim
    {
        return new Claim($this->months);
    }

    /**
     * The FIX: the arrow back into Claims is gone. The policy states its side of the relationship as a
     * contract it OWNS (`CoverageClaim`), the claims desk implements it, and Warranty can now be read,
     * tested and lifted out with nothing from the desk coming along.
     */
    #[Fixed(NamespaceCycle::class)]
    public function coveredMonthsOf(CoverageClaim $claim): int
    {
        return min($claim->claimedMonths(), $this->months);
    }
}
