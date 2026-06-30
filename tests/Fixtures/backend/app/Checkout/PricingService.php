<?php

namespace Shop\Checkout;

use JesseGall\CodeCommandments\Sins\Backend\ArrayReturnBag;

use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Repositories\OrderRepository;

/**
 * Computes an order's price breakdown.
 */
final class PricingService
{
    public function __construct(private readonly OrderRepository $orders) {}

    /**
     * @return array<string, int>
     */
    #[Sinful(ArrayReturnBag::class)]
    public function breakdown(int $orderId): array
    {
        $subtotal = $this->orders->findOrFail($orderId)->total_cents;

        return [
            'subtotal' => $subtotal,
            'tax' => (int) round($subtotal * 0.21),
            'total' => (int) round($subtotal * 1.21),
        ];
    }
}
