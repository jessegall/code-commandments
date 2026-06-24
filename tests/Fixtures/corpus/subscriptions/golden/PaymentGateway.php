<?php

namespace App\Subscriptions;

/**
 * A payment provider that can charge a subscription.
 */
interface PaymentGateway
{
    public function charge(SubscriptionData $subscription, int $amountCents): void;
}
