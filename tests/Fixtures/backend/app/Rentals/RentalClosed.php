<?php

namespace Shop\Rentals;

use RuntimeException;

/**
 * A rental that already ended was asked to extend.
 */
final class RentalClosed extends RuntimeException
{
    public static function forRental(int $id): self
    {
        return new self("Rental {$id} has ended.");
    }
}
