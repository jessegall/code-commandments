<?php

namespace App\NodeLookup;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

// SYMPTOM of the nullable `find`: with no `has`, existence is checked by
// comparing the lookup result against null — the repository's missing total
// surface leaks into every caller's notion of "does it exist".

/**
 * Validates that an inbound node id refers to a node present in the graph.
 */
final class NodeExistsRule implements ValidationRule
{
    public function __construct(
        private readonly NodeRepository $nodes,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $node = $this->nodes->find(new NodeId((string) $value));

        if ($node === null) {
            $fail('The selected :attribute is not a known node.');
        }
    }
}
