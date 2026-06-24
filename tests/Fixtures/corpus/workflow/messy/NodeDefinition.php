<?php

namespace App\Workflow;

use Illuminate\Support\Fluent;
use JesseGall\PhpTypes\T_Array;

class NodeDefinition
{
    /**
     * @param mixed $value
     * @return array<int, NodeOutput>
     */
    public function controlOutputs(mixed $value, bool $withMatch): array
    {
        if (! is_array($value)) {
            return T_Array::empty();
        }

        $outputs = T_Array::empty();

        foreach ($value as $entry) {
            $bag = is_array($entry) ? new Fluent($entry) : null;
            $name = $bag?->get('name') ?? (is_string($entry) ? $entry : null);

            if (! is_string($name)) {
                continue;
            }

            $match = $withMatch ? ($bag?->get('match') ?? $name) : null;
            $outputs[] = new NodeOutput(name: $name, control: true, match: is_string($match) ? $match : null);
        }

        return $outputs;
    }

}
