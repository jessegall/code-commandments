<?php

namespace Shop\Loyalty;

use JesseGall\CodeCommandments\Sins\Backend\HandRolledRegistry;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * The loyalty tiers by the points a member needs to reach each one.
 */
#[Sinful(HandRolledRegistry::class)]
final class TierLadder
{
    public function __construct(private array $tiersByPoints = []) {}

    public function rung(int $points, LoyaltyTier $tier): self
    {
        $this->tiersByPoints[$points] = $tier;
        ksort($this->tiersByPoints);

        return $this;
    }

    public function labelAt(int $points): string
    {
        if (! array_key_exists($points, $this->tiersByPoints)) {
            throw TierMissing::at($points);
        }

        return $this->tiersByPoints[$points]->label();
    }
}
