<?php

namespace App\FeatureEnvy\InvoiceTotal;

/** Computes an invoice total by reaching through its line items. */
final class InvoiceTotalCalculator
{
    public function total(Invoice $inv): float
    {
        return array_reduce(
            $inv->getLines(),
            function (float $carry, LineItem $line): float {
                $net = ($line->getQty() * $line->getUnitPrice()) - $line->getDiscount();

                return $carry + $net + ($net * $line->getTaxRate());
            },
            0.0,
        );
    }
}
