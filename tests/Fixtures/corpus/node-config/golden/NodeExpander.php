<?php

namespace App\NodeConfig;

/**
 * Expands a node id + typed config into the runtime shape the executor uses.
 */
final class NodeExpander
{
    public function expand(string $nodeId, NodeConfig $config): ExpandedNode
    {
        return new ExpandedNode(
            id: $nodeId,
            label: $config->label,
            timeoutMs: $config->timeoutMilliseconds(),
            maxAttempts: $config->retries + 1,
        );
    }
}
