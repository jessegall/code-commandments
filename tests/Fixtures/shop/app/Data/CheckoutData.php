<?php

namespace Shop\Data;

use Spatie\LaravelData\Data;

/**
 * Righteous twin for NewDataObject: the `new CustomerData(...)` here is a
 * constructor parameter DEFAULT — the one place the skill permits `new` for a
 * Data object — so it must NOT be flagged.
 */
final class CheckoutData extends Data
{
    public function __construct(
        public readonly int $totalCents,
        public readonly CustomerData $customer = new CustomerData(0, 'guest', 'guest@shop.test'),
    ) {}
}
