<?php

declare(strict_types=1);

namespace App\Subscriptions;

/**
 * How often a paid subscription renews.
 */
enum BillingCycle: string
{
    /** Charged every month; the default for Pro. */
    case Monthly = 'monthly';

    /** Charged once a year, at a discount. */
    case Annual = 'annual';

    public function months(): int
    {
        return match ($this) {
            BillingCycle::Monthly => 1,
            BillingCycle::Annual => 12,
        };
    }
}
