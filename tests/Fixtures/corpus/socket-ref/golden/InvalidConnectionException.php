<?php

namespace App\SocketRef;

use RuntimeException;

/**
 * Thrown when a connect() is attempted between two sockets that may not be wired.
 */
final class InvalidConnectionException extends RuntimeException
{
    public static function between(SocketRef $source, SocketRef $target): self
    {
        return new self("Cannot connect `{$source->key()}` to `{$target->key()}`.");
    }
}
