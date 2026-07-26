<?php

namespace Shop\Pricing;

use Shop\Events\PriceChanged;

/**
 * The live dispatcher that keeps PriceChanged's listener honest.
 */
final class PriceBroadcaster
{
    public function announce(string $sku, int $cents): void
    {
        event(new PriceChanged($sku, $cents));
    }
}
