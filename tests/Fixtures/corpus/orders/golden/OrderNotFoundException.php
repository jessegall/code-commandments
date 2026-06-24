<?php

declare(strict_types=1);

namespace App\Orders;

use RuntimeException;

/**
 * Thrown when an order is looked up by an id that does not exist.
 */
final class OrderNotFoundException extends RuntimeException
{
    public static function forId(string $id): self
    {
        return new self("No order found for id `{$id}`.");
    }
}
