<?php

namespace Shop\Reporting;

/**
 * One line of a receipt, which answers for its own figures.
 */
final class ReceiptLine
{
    public function __construct(
        private string $label,
        private int $priceCents,
        private int $count,
    ) {
    }

    public function label(): string
    {
        return $this->label;
    }

    public function priceCents(): int
    {
        return $this->priceCents;
    }

    public function count(): int
    {
        return $this->count;
    }

    public function totalCents(): int
    {
        return $this->priceCents * $this->count;
    }
}
