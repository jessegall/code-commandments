<?php

namespace Shop\Invoices;

use JesseGall\CodeCommandments\Sins\Backend\CeremonyDocblock;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Reporting\InvoiceNumberFormatter as Formatter;

final class InvoiceLabel
{
    /**
     * @param Formatter $formatter
     */
    #[Sinful(CeremonyDocblock::class)]
    public function caption(Formatter $formatter, int $sequence): string
    {
        return 'Invoice ' . $formatter->format($sequence);
    }
}
