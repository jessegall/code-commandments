<?php

namespace Shop\Peripherals;

use JesseGall\CodeCommandments\Sins\Backend\UndeclaredMember;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * Wakes the peripherals of a till that sleep between uses, when the till opens.
 */
final class PeripheralWaker
{
    #[Righteous(UndeclaredMember::class)]
    public function wake(TillPeripheral $peripheral): bool
    {
        if ($peripheral instanceof CardReader || $peripheral instanceof ReceiptPrinter) {
            return $peripheral->wake();
        }

        return false;
    }
}
