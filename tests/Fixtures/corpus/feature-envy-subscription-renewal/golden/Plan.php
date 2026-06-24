<?php

namespace App\FeatureEnvy\SubscriptionRenewal;

/**
 * A purchasable plan with a per-month list price.
 */
final readonly class Plan
{
    public function __construct(
        private string $name,
        private int $priceCents,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getPriceCents(): int
    {
        return $this->priceCents;
    }
}
