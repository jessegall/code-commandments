<?php

namespace App\NodeLookup;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that an inbound node id refers to a node present in the graph.
 */
final class NodeExistsRule implements ValidationRule
{
    public function __construct(
        private readonly NodeRegistry $nodes,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $this->nodes->has(new NodeId((string) $value))) {
            $fail('The selected :attribute is not a known node.');
        }
    }
}
