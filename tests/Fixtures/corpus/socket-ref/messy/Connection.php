<?php

namespace App\SocketRef;

/**
 * A directed wire carrying the source/target clump as six loose strings, not two SocketRefs.
 */
final readonly class Connection
{
    public function __construct(
        public string $fromNodeId,
        public string $fromSocketId,
        public string $fromDirection,
        public string $toNodeId,
        public string $toSocketId,
        public string $toDirection,
    ) {}

    public function sourceKey(): string
    {
        return $this->fromNodeId . ':' . $this->fromSocketId . ':' . $this->fromDirection;
    }

    public function targetKey(): string
    {
        return $this->toNodeId . ':' . $this->toSocketId . ':' . $this->toDirection;
    }
}
