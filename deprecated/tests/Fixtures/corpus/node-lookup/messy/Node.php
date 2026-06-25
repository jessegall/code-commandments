<?php

namespace App\NodeLookup;

/**
 * A single node in a workflow graph, identified by its id and typed by kind.
 */
final readonly class Node
{
    public function __construct(
        public NodeId $id,
        public NodeKind $kind,
        public string $name,
    ) {}

    public function canStartRun(): bool
    {
        return $this->kind->canStartRun();
    }
}
