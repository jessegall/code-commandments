<?php

namespace App\FeatureEnvy\SubscriptionRenewal;

/**
 * An optional paid extra attached to a subscription, priced per month.
 */
final readonly class AddOn
{
    public function __construct(
        public string $name,
        public int $priceCents,
    ) {}
}
