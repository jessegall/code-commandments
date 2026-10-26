<?php

namespace Shop\Reporting;

/**
 * The lines a receipt is printed from.
 */
final class Basket
{
    /**
     * @param  list<ReceiptLine>  $lines
     */
    public function __construct(private array $lines)
    {
    }

    /**
     * @return list<ReceiptLine>
     */
    public function lines(): array
    {
        return $this->lines;
    }

    public function totalCents(): int
    {
        $cents = 0;

        foreach ($this->lines as $line) {
            $cents = $cents + $line->totalCents();
        }

        return $cents;
    }
}
