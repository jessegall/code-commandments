<?php

namespace App\SocketRef;

/**
 * Decides whether a source/target SocketRef pair may legally be wired together.
 */
final class ConnectionValidator
{
    public function __construct(
        private readonly SocketRegistry $sockets,
    ) {}

    public function isValid(SocketRef $source, SocketRef $target): bool
    {
        return $source->isSource()
            && ! $source->onSameNodeAs($target)
            && $source->canWireTo($target)
            && $this->sockets->has($source)
            && $this->sockets->has($target);
    }
}
