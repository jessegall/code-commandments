<?php

namespace App\Subscriptions;

/**
 * The subscription tiers a customer can hold.
 */
enum Plan: string
{
    /** No cost, capped usage — the tier every new account starts on. */
    case Free = 'free';

    /** The paid monthly tier for an individual, full feature access. */
    case Pro = 'pro';

    /** A negotiated annual contract for an organisation. */
    case Enterprise = 'enterprise';

    public function monthlyPriceCents(): int
    {
        return match ($this) {
            Plan::Free => 0,
            Plan::Pro => 2_900,
            Plan::Enterprise => 49_900,
        };
    }

    public function isPaid(): bool
    {
        return match ($this) {
            Plan::Free => false,
            Plan::Pro, Plan::Enterprise => true,
        };
    }
}
