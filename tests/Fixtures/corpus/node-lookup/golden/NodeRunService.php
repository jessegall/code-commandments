<?php

namespace App\NodeLookup;

/**
 * Resolves the node a run targets and decides whether it may start the run.
 */
final class NodeRunService
{
    public function __construct(
        private readonly NodeRegistry $nodes,
    ) {}

    public function startFrom(NodeId $id): NodeKind
    {
        $node = $this->nodes->get($id);

        if (! $node->canStartRun()) {
            throw NodeNotFoundException::forId($id);
        }

        return $node->kind;
    }

    public function describe(NodeId $id): string
    {
        return $this->nodes->get($id)->name;
    }
}
