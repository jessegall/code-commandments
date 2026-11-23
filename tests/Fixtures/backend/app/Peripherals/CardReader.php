<?php

namespace Shop\Peripherals;

/**
 * The card reader beside a till, which sleeps between payments.
 */
final class CardReader implements TillPeripheral
{
    private bool $awake = false;

    public function __construct(private readonly string $serial) {}

    public function serial(): string
    {
        return $this->serial;
    }

    public function wake(): bool
    {
        $this->awake = true;

        return $this->awake;
    }
}
