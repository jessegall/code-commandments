<?php

namespace App\NodeLookup;

/**
 * Keyed store of a graph's nodes — total `get` (throws on absence) plus `has`,
 * and a separate `tryGet` only where absence is a genuine, valid outcome.
 */
final class NodeRegistry
{
    /**
     * @var array<string, Node>
     */
    private array $nodes = [];

    public function register(Node $node): void
    {
        $this->nodes[$node->id->value] = $node;
    }

    public function has(NodeId $id): bool
    {
        return isset($this->nodes[$id->value]);
    }

    public function get(NodeId $id): Node
    {
        return $this->nodes[$id->value] ?? throw NodeNotFoundException::forId($id);
    }

    /**
     * The optional lookup, used only when "no such node" is a valid answer
     * (e.g. resolving the node a branch *may* point at). Callers that require
     * the node call `get` instead and never thread a nullable downstream.
     */
    public function tryGet(NodeId $id): ?Node
    {
        return $this->nodes[$id->value] ?? null;
    }
}
