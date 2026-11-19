<?php

namespace Shop\Repairs;

use RuntimeException;

/**
 * A repair ticket was asked to close while it was not open.
 */
final class RepairNotOpen extends RuntimeException
{
    public static function forTicket(string $number): self
    {
        return new self("Repair ticket {$number} is not open.");
    }
}
