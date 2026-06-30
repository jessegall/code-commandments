<?php

namespace Shop\Customers;

use JesseGall\CodeCommandments\Detectors\Backend\CeremonyDocblockDetector;
use JesseGall\CodeCommandments\Testing\Sinful;

final class LoyaltyTier
{
    /**
     * @param  int  $points
     * @param  string  $name
     */
    #[Sinful(CeremonyDocblockDetector::class)]
    public function award(int $points, string $name): string
    {
        return $name . ':' . $points;
    }
}
