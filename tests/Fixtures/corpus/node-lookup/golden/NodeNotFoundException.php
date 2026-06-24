<?php

namespace App\NodeLookup;

use RuntimeException;

/**
 * Thrown when a node is requested by an id that is not present in the graph.
 */
final class NodeNotFoundException extends RuntimeException
{
    public static function forId(NodeId $id): self
    {
        return new self("No node registered for id `{$id->value}`.");
    }
}
