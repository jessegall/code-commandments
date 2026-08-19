<?php

namespace Shop\Kiosk;

use JesseGall\CodeCommandments\Sins\Backend\BlankStringOnTheWire;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * What a customer asked us to do when they collect an order. The kiosk prints the instructions when
 * there are any — and asks the blank whether there are.
 */
#[Sinful(BlankStringOnTheWire::class)]
final class PickupNote
{
    public string $instructions = '';

    public int $printedTimes = 0;

    public function printed(): void
    {
        $this->printedTimes++;
    }

    public function reprintable(): bool
    {
        return $this->printedTimes < 3;
    }
}
