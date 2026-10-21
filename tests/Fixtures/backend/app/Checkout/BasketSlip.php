<?php

namespace Shop\Checkout;

/**
 * The till slip for one basket as the pricer fills it in: what the lines came to before tax,
 * and the tax on top of them.
 */
final class BasketSlip
{
    public int $netCents = 0;

    public int $taxCents = 0;

    public function total(): int
    {
        return $this->netCents + $this->taxCents;
    }
}
