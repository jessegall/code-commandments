<?php

declare(strict_types=1);

namespace App\Subscriptions;

/**
 * The typed, validated intent to start a subscription.
 */
final readonly class SubscriptionData
{
    public function __construct(
        public string $customerId,
        public Plan $plan,
        public BillingCycle $cycle,
    ) {}
}
