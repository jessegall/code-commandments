<?php

namespace App\FeatureEnvy\InvoiceTotal;

/** An invoice composed of billable line items. */
final class Invoice
{
    /** @param list<LineItem> $lines */
    public function __construct(
        public readonly string $number,
        public readonly array $lines,
    ) {}

    public function getNumber(): string
    {
        return $this->number;
    }

    /** @return list<LineItem> */
    public function getLines(): array
    {
        return $this->lines;
    }
}
