<?php

namespace Shop\Checkout;

/**
 * Summarises a checkout by pricing the order and rendering its invoice line.
 */
final class CheckoutSummaryService
{
    public function __construct(
        private readonly PricingService $pricing,
        private readonly InvoiceService $invoice,
    ) {}

    public function summarise(int $orderId): string
    {
        return $this->invoice->render($this->pricing->breakdown($orderId));
    }
}
