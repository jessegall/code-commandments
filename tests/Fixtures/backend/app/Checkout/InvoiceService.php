<?php

namespace Shop\Checkout;

use JesseGall\CodeCommandments\Sins\Backend\ArrayBag;

use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Renders an invoice line from a price breakdown.
 */
final class InvoiceService
{
    /**
     * @param  array<string, int>  $breakdown
     */
    #[Sinful(ArrayBag::class)]
    public function render(array $breakdown): string
    {
        return sprintf(
            'Subtotal %d, tax %d, total %d',
            $breakdown['subtotal'],
            $breakdown['tax'],
            $breakdown['total'],
        );
    }

    #[Righteous(ArrayBag::class)]
    public function renderTotals(PriceBreakdown $breakdown): string
    {
        return sprintf(
            'Subtotal %d, tax %d, total %d',
            $breakdown->subtotal,
            $breakdown->tax,
            $breakdown->total,
        );
    }
}
