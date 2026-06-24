<?php

namespace App\FeatureEnvy\InvoiceTotal;

/** An invoice that owns its own total by asking each line for its subtotal. */
final class Invoice
{
    /** @param list<LineItem> $lines */
    public function __construct(
        public readonly string $number,
        public readonly array $lines,
    ) {}

    public function total(): float
    {
        return array_sum(array_map(
            fn (LineItem $line) => $line->subtotal(),
            $this->lines,
        ));
    }
}
