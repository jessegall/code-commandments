<?php

namespace App\NodeKind;

use RuntimeException;

/**
 * Thrown when a node kind string matches no known arm — but nothing routes
 * through it, since every consumer hard-codes the arm list inline instead.
 */
class NodeKindNotFoundException extends RuntimeException
{
    public static function forKey(string $key): self
    {
        return new self("No node kind registered for `{$key}`.");
    }
}
