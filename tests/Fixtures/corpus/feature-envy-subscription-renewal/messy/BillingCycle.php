<?php

namespace App\FeatureEnvy\SubscriptionRenewal;

/**
 * How often a subscription renews, and how many months that spans.
 */
enum BillingCycle: string
{
    /** Charged every month. */
    case Monthly = 'monthly';

    /** Charged once every three months. */
    case Quarterly = 'quarterly';

    /** Charged once a year. */
    case Annual = 'annual';

    public function getMonths(): int
    {
        return match ($this) {
            BillingCycle::Monthly => 1,
            BillingCycle::Quarterly => 3,
            BillingCycle::Annual => 12,
        };
    }
}
