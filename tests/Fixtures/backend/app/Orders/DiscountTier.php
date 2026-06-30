<?php

namespace Shop\Orders;

use JesseGall\CodeCommandments\Detectors\Backend\IfElseLadderDetector;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Resolves a loyalty discount with a hand-rolled if/elseif ladder over spend.
 */
final class DiscountTier
{
    #[Sinful(IfElseLadderDetector::class)]
    public function percent(int $lifetimeCents): int
    {
        if ($lifetimeCents >= 1_000_000) {
            return 20;
        } elseif ($lifetimeCents >= 250_000) {
            return 10;
        } elseif ($lifetimeCents >= 50_000) {
            return 5;
        }

        return 0;
    }

    public function qualifiesForFreeShipping(int $lifetimeCents): bool
    {
        return $lifetimeCents >= 50_000;
    }
}
