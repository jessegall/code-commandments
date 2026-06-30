<?php

namespace Shop\Customers;

use JesseGall\CodeCommandments\Sins\Backend\CeremonyDocblock;

use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

final class LoyaltyTier
{
    /**
     * @param  int  $points
     * @param  string  $name
     */
    #[Sinful(CeremonyDocblock::class)]
    public function award(int $points, string $name): string
    {
        return $name . ':' . $points;
    }

    /**
     * Renders the customer-facing tier label, e.g. "gold:500".
     */
    #[Righteous(CeremonyDocblock::class)]
    public function awardLabel(int $points, string $name): string
    {
        return $name . ':' . $points;
    }
}
