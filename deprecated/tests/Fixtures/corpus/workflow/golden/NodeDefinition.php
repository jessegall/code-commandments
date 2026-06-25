<?php

namespace App\Workflow;

/**
 * Builds the control outputs a workflow node declares.
 */
final class NodeDefinition
{
    /**
     * @param array<int, string|array<string, mixed>> $specs
     * @return list<NodeOutput>
     */
    public function controlOutputs(array $specs, bool $withMatch): array
    {
        $outputs = [];
        foreach ($specs as $spec) {
            $bag = OutputSpec::coalesce($spec);
            $name = $bag->name();
            if ($name === '') {
                continue;
            }
            $outputs[] = new NodeOutput(
                name: $name,
                control: true,
                match: $withMatch ? $bag->matchOr($name) : null,
            );
        }
        return $outputs;
    }
}
