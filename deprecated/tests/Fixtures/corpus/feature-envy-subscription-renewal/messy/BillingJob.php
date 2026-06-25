<?php

namespace App\FeatureEnvy\SubscriptionRenewal;

use DateTimeImmutable;

/**
 * Nightly job that charges subscriptions due for renewal.
 */
final class BillingJob
{
    public function chargeAmount(Subscription $subscription): Money
    {
        // Reaches THROUGH the subscription into the plan and cycle to do
        // arithmetic that is really the subscription's own business.
        $perMonth = $subscription->getPlan()->getPriceCents();
        $months = $subscription->getCycle()->getMonths();

        // Worse: runs a reduce over the subscription's OWN add-on collection,
        // summing a total that the subscription should compute itself.
        $addOnCents = array_reduce(
            $subscription->addOns,
            fn (int $carry, AddOn $addOn) => $carry + $addOn->priceCents,
            0,
        );

        return new Money(($perMonth + $addOnCents) * $months, 'USD');
    }

    public function nextChargeDate(Subscription $subscription): DateTimeImmutable
    {
        // Again reaches into the subscription's fields to compute the date
        // that the subscription should own.
        $months = $subscription->getCycle()->getMonths();

        return $subscription->getStartedAt()->modify("+{$months} months");
    }

    public function run(Subscription $subscription): string
    {
        $amount = $this->chargeAmount($subscription);
        $on = $this->nextChargeDate($subscription);

        return sprintf(
            'Charging %s on %s',
            $amount->format(),
            $on->format('Y-m-d'),
        );
    }
}
