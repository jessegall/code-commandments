<?php

namespace Shop\Loyalty;

/**
 * A member's account summary — surfaces the tier for the views above it.
 */
final class AccountSummary
{
    public function __construct(private readonly Member $member) {}

    public function tier(): ?LoyaltyTier
    {
        return $this->member->currentTier();
    }
}
