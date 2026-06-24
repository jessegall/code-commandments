<?php

namespace App\SocketRef;

/**
 * Keyed store of the sockets a graph has declared; resolves a ref or throws.
 */
final class SocketRegistry
{
    /**
     * @var array<string, SocketRef>
     */
    private array $sockets = [];

    public function declare(SocketRef $ref): void
    {
        $this->sockets[$ref->key()] = $ref;
    }

    public function has(SocketRef $ref): bool
    {
        return isset($this->sockets[$ref->key()]);
    }

    public function get(SocketRef $ref): SocketRef
    {
        return $this->sockets[$ref->key()] ?? throw SocketNotFoundException::forRef($ref);
    }
}
