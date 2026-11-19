<?php

namespace Shop\Giftwrap;

/**
 * The wrapping line on a receipt.
 */
final class WrapCounter
{
    public function __construct(private readonly WrapPricer $pricer) {}

    public function receiptLine(int $parcels): string
    {
        return sprintf('Gift wrap x%d: %d', $parcels, $this->pricer->cents($parcels, true));
    }
}
