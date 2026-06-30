<?php

namespace Shop\Shipping;

use JesseGall\CodeCommandments\Sins\Backend\NestedTernary;

use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Resolves a courier SLA. The estimate folds three branches into one ternary
 * chain — the precedence is a trap and the decision is invisible.
 */
final class DeliveryWindow
{
    public function __construct(private readonly bool $express = false) {}

    #[Sinful(NestedTernary::class)]
    public function estimateDays(int $distanceKm): int
    {
        return $this->express
            ? ($distanceKm > 500 ? 2 : 1)
            : ($distanceKm > 500 ? 5 : 3);
    }
}
