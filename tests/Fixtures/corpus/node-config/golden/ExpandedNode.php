<?php

namespace App\NodeConfig;

/**
 * A node expanded into the runtime shape the executor schedules against.
 */
final readonly class ExpandedNode
{
    public function __construct(
        public string $id,
        public string $label,
        public int $timeoutMs,
        public int $maxAttempts,
    ) {}
}
