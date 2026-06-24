<?php

declare(strict_types=1);

namespace App\Inventory;

use RuntimeException;

/**
 * Thrown when a warehouse is requested by a code that was never registered.
 */
final class WarehouseNotFoundException extends RuntimeException
{
    public static function forKey(string $key): self
    {
        return new self("No warehouse registered for `{$key}`.");
    }
}
