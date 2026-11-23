<?php

namespace Shop\Peripherals;

/**
 * The receipt printer of a till, which warms its head before it prints.
 */
final class ReceiptPrinter implements TillPeripheral
{
    private int $warmings = 0;

    public function __construct(private readonly string $serial) {}

    public function serial(): string
    {
        return $this->serial;
    }

    public function wake(): bool
    {
        $this->warmings++;

        return $this->warmings > 0;
    }
}
