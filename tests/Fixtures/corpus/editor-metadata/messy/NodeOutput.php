<?php

namespace App\EditorMetadata;

/**
 * A declared output handle on an editor node. (Same value object as the golden
 * twin — the leaf is fine; the rot is everything that has to produce one.)
 */
final readonly class NodeOutput
{
    public function __construct(
        public string $name,
        public bool $control,
        public ?string $match,
    ) {}
}
