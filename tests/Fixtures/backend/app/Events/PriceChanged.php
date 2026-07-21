<?php

namespace Shop\Events;

/** A price moved. Still raised by the pricing service, so its listener is live wiring. */
final readonly class PriceChanged
{
    public function __construct(public string $sku, public int $cents) {}
}
