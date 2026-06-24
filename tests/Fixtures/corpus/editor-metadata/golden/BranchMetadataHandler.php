<?php

namespace App\EditorMetadata;

/**
 * Turns a node's typed branch specs into its declared output handles.
 */
final class BranchMetadataHandler
{
    /**
     * @return list<NodeOutput>
     */
    public function apply(SetBranchesPayload $payload): array
    {
        $outputs = [];

        foreach ($payload->branches as $branch) {
            $name = $branch->label();

            if ($name === '') {
                continue;
            }

            $outputs[] = new NodeOutput(
                name: $name,
                control: true,
                match: $branch->withMatch() ? $name : null,
            );
        }

        return $outputs;
    }
}
