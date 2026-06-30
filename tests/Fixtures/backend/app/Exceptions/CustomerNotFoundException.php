<?php

namespace Shop\Exceptions;

use RuntimeException;

final class CustomerNotFoundException extends RuntimeException
{
    public static function forEmail(string $email): self
    {
        return new self("No customer is registered with email '{$email}'.");
    }
}
