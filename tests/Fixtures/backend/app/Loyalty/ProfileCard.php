<?php

namespace Shop\Loyalty;

/**
 * The profile card view model — passes the tier up from the account summary.
 */
final class ProfileCard
{
    public function __construct(private readonly AccountSummary $summary) {}

    public function tier(): ?LoyaltyTier
    {
        return $this->summary->tier();
    }
}
