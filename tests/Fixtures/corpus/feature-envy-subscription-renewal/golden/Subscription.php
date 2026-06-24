<?php

namespace App\FeatureEnvy\SubscriptionRenewal;

use DateTimeImmutable;

/**
 * An active subscription that owns its own renewal pricing and schedule.
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
        private array $addOns = [],
    ) {}

    public function nextCharge(): Money
    {
        $perMonth = $this->plan->getPriceCents() + $this->addOnCents();

        return new Money($perMonth * $this->cycle->getMonths(), 'USD');
    }

    public function renewsOn(): DateTimeImmutable
    {
        return $this->startedAt->modify("+{$this->cycle->getMonths()} months");
    }

    private function addOnCents(): int
    {
        return array_reduce(
            $this->addOns,
            fn (int $carry, AddOn $addOn) => $carry + $addOn->priceCents,
            0,
        );
    }
}
