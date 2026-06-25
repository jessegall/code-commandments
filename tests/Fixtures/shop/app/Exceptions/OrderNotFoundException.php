<?php

namespace Shop\Exceptions;

use RuntimeException;

final class OrderNotFoundException extends RuntimeException
{
    public static function forId(int $id): self
    {
        return new self("No order exists with id {$id}.");
    }
}
