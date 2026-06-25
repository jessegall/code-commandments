<?php

namespace App\NodeConfig;

/**
 * Expands a node id + raw config array into the runtime shape the executor uses.
 */
final class NodeExpander
{
    /**
     * SYMPTOM #1: re-reads timeout/retries/label out of the array and re-applies
     * the SAME coalesce defaults that the validator and presenter also duplicate.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function expand(string $nodeId, array $config): array
    {
        $timeout = $config['timeout'] ?? 30;
        $retries = $config['retries'] ?? 0;
        $label = $config['label'] ?? 'Untitled node';

        if ($label === '') {
            $label = 'Untitled node';
        }

        return [
            'id' => $nodeId,
            'label' => $label,
            'timeoutMs' => $timeout * 1000,
            'maxAttempts' => $retries + 1,
        ];
    }
}
