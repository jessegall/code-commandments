<?php

namespace Shop\Loyalty;

/**
 * A membership tier and its display name.
 */
final class LoyaltyTier
{
    public function __construct(private readonly string $name) {}

    public function label(): string
    {
        return $this->name;
    }
}
