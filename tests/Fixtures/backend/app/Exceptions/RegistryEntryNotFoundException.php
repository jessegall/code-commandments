<?php

namespace Shop\Exceptions;

use RuntimeException;

final class RegistryEntryNotFoundException extends RuntimeException
{
    public static function forKey(string $key): self
    {
        return new self("No item is registered under the key '{$key}'.");
    }
}
