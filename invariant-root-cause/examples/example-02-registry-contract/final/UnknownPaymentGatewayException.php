<?php

declare(strict_types=1);

namespace Billing\Gateways;

/**
 * ADDED CLASS. A named exception (via static factory) so the failure reads
 * clearly at the throw site and callers can catch it precisely if they ever
 * need to. Replaces the silent `null` that `find()` used to return.
 */
final class UnknownPaymentGatewayException extends \RuntimeException
{
    public static function forKey(string $key): self
    {
        return new self("No payment gateway is registered for key '{$key}'.");
    }
}
