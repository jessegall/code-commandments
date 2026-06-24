<?php

namespace App\NodeKind;

use RuntimeException;

/**
 * Thrown when a node kind is requested by a key that was never registered.
 */
final class NodeKindNotFoundException extends RuntimeException
{
    public static function forKey(string $key): self
    {
        return new self("No node kind registered for `{$key}`.");
    }
}
