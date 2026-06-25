<?php

namespace App\SocketRef;

/**
 * Wires two declared sockets together, taking the clump as six positional scalars.
 */
final class Connector
{
    public function __construct(
        private readonly ConnectionValidator $validator,
        private readonly SocketRegistry $sockets,
    ) {}

    /**
     * @return array<string, string>
     */
    public function connect(
        string $fromNodeId,
        string $fromSocketId,
        string $fromDirection,
        string $toNodeId,
        string $toSocketId,
        string $toDirection,
    ): array {
        $valid = $this->validator->isValid(
            $fromNodeId,
            $fromSocketId,
            $fromDirection,
            $toNodeId,
            $toSocketId,
            $toDirection,
        );

        if (! $valid) {
            throw new \RuntimeException(
                "Cannot connect `{$fromNodeId}:{$fromSocketId}:{$fromDirection}` to `{$toNodeId}:{$toSocketId}:{$toDirection}`."
            );
        }

        // Coerce both endpoints through the registry as loose-key lookups, then
        // re-clump the whole thing into yet another stringly-keyed array bag.
        $source = $this->sockets->get($fromNodeId, $fromSocketId, $fromDirection);
        $target = $this->sockets->get($toNodeId, $toSocketId, $toDirection);

        return [
            'from_node' => $source['node_id'] ?? '',
            'from_socket' => $source['socket_id'] ?? '',
            'from_direction' => $source['direction'] ?? '',
            'to_node' => $target['node_id'] ?? '',
            'to_socket' => $target['socket_id'] ?? '',
            'to_direction' => $target['direction'] ?? '',
        ];
    }
}
