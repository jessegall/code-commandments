<?php

namespace Shop\Warranty;

use JesseGall\CodeCommandments\Sins\Backend\NamespaceCycle;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Claims\Claim;

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
}
