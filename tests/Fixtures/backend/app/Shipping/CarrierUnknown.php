<?php

namespace Shop\Shipping;

use RuntimeException;

/**
 * A carrier code nothing registered.
 */
final class CarrierUnknown extends RuntimeException
{
    public static function forCode(string $code): self
    {
        return new self("No carrier is registered under '{$code}'.");
    }
}
