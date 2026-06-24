<?php

namespace App\FeatureEnvy\InvoiceTotal;

/** A single billable line that owns its own subtotal math. */
final class LineItem
{
    public function __construct(
        public readonly string $description,
        public readonly int $qty,
        public readonly float $unitPrice,
        public readonly float $discount,
        public readonly float $taxRate,
    ) {}

    public function subtotal(): float
    {
        $net = ($this->qty * $this->unitPrice) - $this->discount;

        return $net + ($net * $this->taxRate);
    }
}
