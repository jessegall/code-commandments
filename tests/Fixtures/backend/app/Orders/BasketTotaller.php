<?php

namespace Shop\Orders;

use JesseGall\CodeCommandments\Detectors\Backend\FeatureEnvyDetector;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Sums the basket's own line amounts — iterating the basket's collection to derive
 * a total the basket could give itself (`$basket->total()`).
 */
final class BasketTotaller
{
    #[Sinful(FeatureEnvyDetector::class)]
    public function total(Basket $basket): int
    {
        $sum = 0;

        foreach ($basket->amounts as $amount) {
            $sum += $amount;
        }

        return $sum;
    }
}
