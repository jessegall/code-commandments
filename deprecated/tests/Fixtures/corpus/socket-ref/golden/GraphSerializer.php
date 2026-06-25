<?php

namespace App\SocketRef;

use Illuminate\Support\Collection;

/**
 * Serializes connections to the persisted graph shape by reading each SocketRef directly.
 */
final class GraphSerializer
{
    /**
     * @param Collection<int, Connection> $connections
     * @return array<int, array<string, string>>
     */
    public function serialize(Collection $connections): array
    {
        return $connections
            ->map($this->endpoint(...))
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function endpoint(Connection $connection): array
    {
        return [
            'from_node' => $connection->source->nodeId,
            'from_socket' => $connection->source->socketId,
            'to_node' => $connection->target->nodeId,
            'to_socket' => $connection->target->socketId,
            'direction' => $connection->source->direction->value,
        ];
    }
}
