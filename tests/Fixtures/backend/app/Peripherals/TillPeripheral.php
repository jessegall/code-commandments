<?php

namespace Shop\Peripherals;

/**
 * A device plugged into a till, known by its serial number.
 */
interface TillPeripheral
{
    public function serial(): string;
}
