<?php

namespace App\EditorMetadata;

/**
 * The typed intent to set a node's branches — loose shorthands normalised at
 * construction into a list of typed BranchSpec value objects.
 */
final readonly class SetBranchesPayload
{
    /**
     * @param list<BranchSpec> $branches
     */
    public function __construct(
        public string $nodeId,
        public array $branches,
    ) {}

    /**
     * Boundary factory: coalesce each loose branch shorthand into a BranchSpec
     * exactly once, so every downstream reader gets typed values.
     *
     * @param list<string|array<string, mixed>> $rawBranches
     */
    public static function from(string $nodeId, array $rawBranches): self
    {
        $branches = collect($rawBranches)
            ->map(BranchSpec::coalesce(...))
            ->values()
            ->all();

        return new self($nodeId, $branches);
    }
}
