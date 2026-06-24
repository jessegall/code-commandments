<?php

namespace App\SocketRef;

/**
 * A typed reference to one socket on one node — the single value passed wherever
 * a (nodeId, socketId, direction) triple used to be threaded as loose strings.
 */
final readonly class SocketRef
{
    public function __construct(
        public string $nodeId,
        public string $socketId,
        public Direction $direction,
    ) {}

    public function isSource(): bool
    {
        return $this->direction->isSource();
    }

    public function onSameNodeAs(SocketRef $other): bool
    {
        return $this->nodeId === $other->nodeId;
    }

    public function canWireTo(SocketRef $other): bool
    {
        return $this->direction === $other->direction->opposite();
    }

    public function key(): string
    {
        return "{$this->nodeId}:{$this->socketId}:{$this->direction->value}";
    }
}
