<?php

namespace App\FeatureEnvy\SubscriptionRenewal;

use DateTimeImmutable;

/**
 * An active subscription tying a customer to a plan on a billing cycle.
 */
final class Subscription
{
    /**
     * @param  list<AddOn>  $addOns
     */
    public function __construct(
        private Plan $plan,
        private BillingCycle $cycle,
        private DateTimeImmutable $startedAt,
        public array $addOns = [],
    ) {}

    public function getPlan(): Plan
    {
        return $this->plan;
    }

    public function getCycle(): BillingCycle
    {
        return $this->cycle;
    }

    public function getStartedAt(): DateTimeImmutable
    {
        return $this->startedAt;
    }
}
