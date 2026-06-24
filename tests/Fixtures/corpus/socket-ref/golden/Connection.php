<?php

namespace App\SocketRef;

/**
 * A directed wire between an output socket (source) and an input socket (target).
 */
final readonly class Connection
{
    public function __construct(
        public SocketRef $source,
        public SocketRef $target,
    ) {}
}
