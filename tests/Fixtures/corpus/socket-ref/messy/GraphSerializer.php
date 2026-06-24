<?php

namespace App\SocketRef;

use Illuminate\Support\Collection;

/**
 * Serializes the array-bag connections by reaching into stringly-keyed slots.
 */
final class GraphSerializer
{
    /**
     * @param Collection<int, array<string, string>> $connections
     * @return array<int, array<string, string>>
     */
    public function serialize(Collection $connections): array
    {
        return $connections
            ->map(fn (array $connection): array => $this->endpoint($connection))
            ->all();
    }

    /**
     * @param array<string, string> $connection
     * @return array<string, string>
     */
    private function endpoint(array $connection): array
    {
        $direction = $connection['from_direction'] ?? '';

        // Third copy of the direction ladder — turn the loose string into a label.
        $label = match ($direction) {
            'input' => 'Input',
            'output' => 'Output',
            default => 'Unknown',
        };

        return [
            'from_node' => $connection['from_node'] ?? '',
            'from_socket' => $connection['from_socket'] ?? '',
            'to_node' => $connection['to_node'] ?? '',
            'to_socket' => $connection['to_socket'] ?? '',
            'direction' => $direction,
            'direction_label' => $label,
        ];
    }
}
