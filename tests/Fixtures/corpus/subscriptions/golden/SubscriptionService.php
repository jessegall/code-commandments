<?php

declare(strict_types=1);

namespace App\Subscriptions;

/**
 * Starts a subscription by charging the customer's first period.
 */
final class SubscriptionService
{
    public function __construct(
        private readonly PaymentGateway $gateway,
    ) {}

    public function start(SubscriptionData $subscription): void
    {
        $amountCents = $subscription->plan->monthlyPriceCents() * $subscription->cycle->months();

        $this->gateway->charge($subscription, $amountCents);
    }
}
