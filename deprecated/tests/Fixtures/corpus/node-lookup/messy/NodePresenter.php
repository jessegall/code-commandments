<?php

namespace App\NodeLookup;

// SYMPTOM of the nullable `find`: the presenter can't trust the lookup, so it
// threads the null through an optional chain and coalesces a placeholder. The
// label silently lies ("Unknown node") instead of the lookup being total.

/**
 * Renders a node's editor label.
 */
final class NodePresenter
{
    public function __construct(
        private readonly NodeRepository $nodes,
    ) {}

    public function label(NodeId $id): string
    {
        $node = $this->nodes->find($id);

        $kind = $node?->kind->label() ?? 'Unknown';
        $name = $node?->name ?? 'Unknown node';

        return "{$kind}: {$name}";
    }
}
