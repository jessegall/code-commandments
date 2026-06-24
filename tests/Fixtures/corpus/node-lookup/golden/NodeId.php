<?php

namespace App\NodeLookup;

/**
 * The stable identity of a node within a workflow graph.
 */
final readonly class NodeId
{
    public function __construct(
        public string $value,
    ) {}

    public function equals(NodeId $other): bool
    {
        return $this->value === $other->value;
    }
}
