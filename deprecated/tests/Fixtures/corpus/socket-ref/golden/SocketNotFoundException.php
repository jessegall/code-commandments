<?php

namespace App\SocketRef;

use RuntimeException;

/**
 * Thrown when a SocketRef points at a socket the graph never declared.
 */
final class SocketNotFoundException extends RuntimeException
{
    public static function forRef(SocketRef $ref): self
    {
        return new self("No socket declared at `{$ref->key()}`.");
    }
}
