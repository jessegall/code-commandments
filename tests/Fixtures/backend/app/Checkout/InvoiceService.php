<?php

namespace Shop\Checkout;

use JesseGall\CodeCommandments\Detectors\Backend\ArrayBagDetector;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Renders an invoice line from a price breakdown.
 */
final class InvoiceService
{
    /**
     * @param  array<string, int>  $breakdown
     */
    #[Sinful(ArrayBagDetector::class)]
    public function render(array $breakdown): string
    {
        return sprintf(
            'Subtotal %d, tax %d, total %d',
            $breakdown['subtotal'],
            $breakdown['tax'],
            $breakdown['total'],
        );
    }
}
