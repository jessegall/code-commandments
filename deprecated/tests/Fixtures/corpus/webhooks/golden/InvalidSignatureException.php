<?php

namespace App\Webhooks;

use RuntimeException;

/**
 * Thrown when an inbound webhook's signature fails verification.
 */
final class InvalidSignatureException extends RuntimeException
{
    public static function mismatch(): self
    {
        return new self('The webhook signature did not match the computed digest.');
    }
}
