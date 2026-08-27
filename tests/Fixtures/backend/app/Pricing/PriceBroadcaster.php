<?php

namespace Shop\Pricing;

use Shop\Events\PriceChanged;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\DeadEventWiring;
use JesseGall\CodeCommandments\Testing\Fixed;

/**
 * The live dispatcher that keeps PriceChanged's listener honest.
 */
#[Fixed(DeadEventWiring::class)]
final class PriceBroadcaster
{
    public function announce(string $sku, int $cents): void
    {
        event(new PriceChanged($sku, $cents));
    }
}
