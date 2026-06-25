<?php

namespace App\FeatureEnvy\SubscriptionRenewal;

/**
 * Nightly job that charges subscriptions due for renewal.
 */
final class BillingJob
{
    public function run(Subscription $subscription): string
    {
        return sprintf(
            'Charging %s on %s',
            $subscription->nextCharge()->format(),
            $subscription->renewsOn()->format('Y-m-d'),
        );
    }
}
