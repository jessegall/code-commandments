<?php

namespace App\FeatureEnvy\InvoiceTotal;

/** Thin entry point that delegates the total to the invoice itself. */
final class InvoiceTotalCalculator
{
    public function total(Invoice $inv): float
    {
        return $inv->total();
    }
}
