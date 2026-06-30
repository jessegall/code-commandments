<?php

namespace Shop\Exceptions;

use RuntimeException;

final class ProductNotFoundException extends RuntimeException
{
    public static function forId(int $id): self
    {
        return new self("No product exists with id {$id}.");
    }
}
