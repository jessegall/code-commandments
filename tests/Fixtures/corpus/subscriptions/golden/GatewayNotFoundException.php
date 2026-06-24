<?php

namespace App\Subscriptions;

use RuntimeException;

/**
 * Thrown when a gateway is requested by a key that was never registered.
 */
final class GatewayNotFoundException extends RuntimeException
{
    public static function forKey(string $key): self
    {
        return new self("No payment gateway registered for `{$key}`.");
    }
}
