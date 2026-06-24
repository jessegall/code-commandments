<?php

namespace App\NodeLookup;

// ROOT SMELL: `find` returns a NULLABLE Node. Absence isn't an error here — it
// just hands the caller a null and walks away. Every consumer that actually
// needs the node then re-invents its own way to cope with the null, so the one
// missing boundary decision (get-or-throw) fans out into a different null-guard
// in every file downstream.

/**
 * Keyed store of a graph's nodes.
 */
final class NodeRepository
{
    /**
     * @var array<string, Node>
     */
    private array $nodes = [];

    public function register(Node $node): void
    {
        $this->nodes[$node->id->value] = $node;
    }

    public function find(NodeId $id): ?Node
    {
        return $this->nodes[$id->value] ?? null;
    }

    /**
     * @return array<string, Node>
     */
    public function all(): array
    {
        return $this->nodes;
    }
}
