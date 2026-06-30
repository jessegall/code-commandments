<?php

namespace Shop\Shipping;

use Shop\Enums\ShippingMethod;
use Shop\Repositories\OrderRepository;
use Shop\Support\CurrencyFormatter;

/**
 * Quotes shipping for an order.
 */
final class ShippingQuoteService
{
    public function __construct(private readonly OrderRepository $orders) {}

    public function quote(int $orderId, ShippingMethod $method, int $weightGrams): string
    {
        $order = $this->orders->findOrFail($orderId);
        $cents = $method->rateCents($weightGrams);

        return (new CurrencyFormatter)->format($cents, 'EUR');
    }
}
