<?php

namespace Shop\Loyalty;

/**
 * The dashboard header — passes the tier up from the profile card.
 */
final class DashboardHeader
{
    public function __construct(private readonly ProfileCard $profile) {}

    public function tier(): ?LoyaltyTier
    {
        return $this->profile->tier();
    }
}
