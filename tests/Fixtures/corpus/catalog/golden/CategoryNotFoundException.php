<?php

declare(strict_types=1);

namespace App\Catalog;

use RuntimeException;

/**
 * Thrown when a category is requested by a key that was never registered.
 */
final class CategoryNotFoundException extends RuntimeException
{
    public static function forKey(string $key): self
    {
        return new self("No category registered for `{$key}`.");
    }
}
