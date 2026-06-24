<?php

namespace App\NodeLookup;

/**
 * Renders a node's editor label by reading the resolved node directly.
 */
final class NodePresenter
{
    public function __construct(
        private readonly NodeRegistry $nodes,
    ) {}

    public function label(NodeId $id): string
    {
        $node = $this->nodes->get($id);

        return "{$node->kind->label()}: {$node->name}";
    }
}
