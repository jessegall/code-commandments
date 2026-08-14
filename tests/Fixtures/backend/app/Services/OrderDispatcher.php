<?php

namespace Shop\Services;

use Shop\Data\CheckoutData;
use Shop\Data\CustomerData;

/**
 * Righteous twin for NewDataObject: the nested `CustomerData` slot is handed a CustomerData the
 * caller already built. `::from()` is a boundary DECODE — over our own constructed value it would
 * only round-trip it, losing whatever the shape cannot carry — so `new` is the honest construction
 * here and the detector must leave it alone (#481).
 */
final class OrderDispatcher
{
    public function dispatch(CustomerData $customer, int $totalCents): CheckoutData
    {
        return new CheckoutData(
            totalCents: $totalCents,
            customer: $customer,
        );
    }
}
