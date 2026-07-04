<?php

namespace Shop\Loyalty;

/**
 * The badge finally rendered in the UI — reads the tier as present to print its label, five view
 * models up from the member.
 */
final class LoyaltyBadge
{
    public function __construct(private readonly DashboardHeader $header) {}

    public function render(): string
    {
        return $this->header->tier()->label();
    }
}
