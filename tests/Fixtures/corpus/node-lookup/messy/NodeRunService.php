<?php

namespace App\NodeLookup;

use RuntimeException;

// SYMPTOM of the nullable `find`: every method that needs the node has to
// re-derive "it must exist" itself — here a null-coalesce-throw and a separate
// null guard. None of this would exist if the repository were total.

/**
 * Resolves the node a run targets and decides whether it may start the run.
 */
final class NodeRunService
{
    public function __construct(
        private readonly NodeRepository $nodes,
    ) {}

    public function startFrom(NodeId $id): NodeKind
    {
        $node = $this->nodes->find($id) ?? throw new RuntimeException("No node `{$id->value}`.");

        if (! $node->canStartRun()) {
            throw new RuntimeException("Node `{$id->value}` cannot start a run.");
        }

        return $node->kind;
    }

    public function describe(NodeId $id): string
    {
        $node = $this->nodes->find($id);

        if ($node === null) {
            throw new RuntimeException("No node `{$id->value}`.");
        }

        return $node->name;
    }
}
