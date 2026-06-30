<?php

namespace Shop\Exceptions;

use RuntimeException;

final class PaymentDeclinedException extends RuntimeException
{
    public static function forToken(string $token): self
    {
        return new self("The payment token '{$token}' was declined.");
    }
}
