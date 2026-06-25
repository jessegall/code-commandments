<?php

namespace App\SocketRef;

/**
 * Decides whether a source/target socket pair may legally be wired together.
 */
final class ConnectionValidator
{
    public function __construct(
        private readonly SocketRegistry $sockets,
    ) {}

    public function isValid(
        string $fromNodeId,
        string $fromSocketId,
        string $fromDirection,
        string $toNodeId,
        string $toSocketId,
        string $toDirection,
    ): bool {
        // "is the source actually a source?" — a literal string compare.
        $sourceIsSource = $fromDirection === 'output';

        // "same node?" — by hand.
        $sameNode = $fromNodeId === $toNodeId;

        // "can these two wire together?" — duplicated direction ladder, opposite-of.
        $opposite = match ($fromDirection) {
            'input' => 'output',
            'output' => 'input',
            default => '',
        };
        $canWire = $opposite === $toDirection;

        return $sourceIsSource
            && ! $sameNode
            && $canWire
            && $this->sockets->has($fromNodeId, $fromSocketId, $fromDirection)
            && $this->sockets->has($toNodeId, $toSocketId, $toDirection);
    }
}
