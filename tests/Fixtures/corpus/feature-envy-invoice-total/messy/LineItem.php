<?php

namespace App\FeatureEnvy\InvoiceTotal;

/** A single billable line on an invoice. */
final class LineItem
{
    public function __construct(
        public readonly string $description,
        public readonly int $qty,
        public readonly float $unitPrice,
        public readonly float $discount,
        public readonly float $taxRate,
    ) {}

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getQty(): int
    {
        return $this->qty;
    }

    public function getUnitPrice(): float
    {
        return $this->unitPrice;
    }

    public function getDiscount(): float
    {
        return $this->discount;
    }

    public function getTaxRate(): float
    {
        return $this->taxRate;
    }
}
