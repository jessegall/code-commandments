<?php

namespace App\SocketRef;

/**
 * Keyed store of sockets, taking the loose triple and storing each as an array bag.
 */
final class SocketRegistry
{
    /**
     * @var array<string, array<string, string>>
     */
    private array $sockets = [];

    public function declare(string $nodeId, string $socketId, string $direction): void
    {
        $key = $nodeId . ':' . $socketId . ':' . $direction;

        $this->sockets[$key] = [
            'node_id' => $nodeId,
            'socket_id' => $socketId,
            'direction' => $direction,
        ];
    }

    public function has(string $nodeId, string $socketId, string $direction): bool
    {
        $key = $nodeId . ':' . $socketId . ':' . $direction;

        return isset($this->sockets[$key]);
    }

    /**
     * @return array<string, string>
     */
    public function get(string $nodeId, string $socketId, string $direction): array
    {
        $key = $nodeId . ':' . $socketId . ':' . $direction;

        // Returns a loose array bag, with a ?? default so the caller never knows
        // whether the socket really existed.
        return $this->sockets[$key] ?? [
            'node_id' => $nodeId,
            'socket_id' => $socketId,
            'direction' => $direction,
        ];
    }
}
