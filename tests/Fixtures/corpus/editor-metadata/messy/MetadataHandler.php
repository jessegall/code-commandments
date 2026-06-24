<?php

namespace App\EditorMetadata;

/**
 * Applies a set-node-metadata payload to a node's declared outputs.
 *
 * SYMPTOM (the leaf where the smell screams loudest): because `$payload->value`
 * is `mixed`, this handler can't just MAP a typed thing to a NodeOutput — it has
 * to branch on the aspect string with a ~6-arm match, and EACH arm re-coerces
 * the same `mixed` differently (is_string ternary here, an array-or-null-or-
 * string walk in controlOutputs(), an is_array guard in specs()). The defensive
 * code is all symptom: type `value` at the boundary and every arm collapses to a
 * one-line read.
 */
class MetadataHandler
{
    /**
     * @return list<NodeOutput>
     */
    public function applyAspect(SetNodeMetadataPayload $payload): array
    {
        $value = $payload->value;

        return match ($payload->aspect) {
            'label' => $this->labelOutput($value),
            'branches' => $this->controlOutputs($value),
            'specs' => $this->specs($value),
            'match' => $this->matchOutput($value),
            'fallthrough' => $this->fallthrough($value),
            default => [],
        };
    }

    /**
     * @return list<NodeOutput>
     */
    private function labelOutput(mixed $value): array
    {
        // re-coerce the mixed: string ternary
        $name = is_string($value) ? $value : '';

        if ($name === '') {
            return [];
        }

        return [new NodeOutput(name: $name, control: false, match: null)];
    }

    /**
     * @return list<NodeOutput>
     */
    private function controlOutputs(mixed $value): array
    {
        // re-coerce the mixed AGAIN, differently: it might be a list of
        // name-strings, a list of attribute-arrays, a single string, or null.
        $branches = is_array($value) ? $value : ($value === null ? [] : [$value]);

        $outputs = [];

        foreach ($branches as $branch) {
            // the ValueBag coalesce dance — string shorthand OR attr array OR junk
            if (is_string($branch)) {
                $name = $branch;
                $withMatch = false;
            } elseif (is_array($branch)) {
                $name = isset($branch['label']) && is_string($branch['label'])
                    ? $branch['label']
                    : ($branch['name'] ?? '');
                $withMatch = (bool) ($branch['match'] ?? false);
            } else {
                $name = '';
                $withMatch = false;
            }

            if (! is_string($name) || $name === '') {
                continue;
            }

            $outputs[] = new NodeOutput(
                name: $name,
                control: true,
                match: $withMatch ? $name : null,
            );
        }

        return $outputs;
    }

    /**
     * @return list<NodeOutput>
     */
    private function specs(mixed $value): array
    {
        // re-coerce the mixed a THIRD way: an is_array guard then index-walk
        if (! is_array($value)) {
            return [];
        }

        $outputs = [];

        foreach ($value as $key => $spec) {
            $name = is_string($key) ? $key : (is_string($spec) ? $spec : '');

            if ($name === '') {
                continue;
            }

            $outputs[] = new NodeOutput(name: $name, control: true, match: null);
        }

        return $outputs;
    }

    /**
     * @return list<NodeOutput>
     */
    private function matchOutput(mixed $value): array
    {
        $name = is_string($value) ? $value : '';

        if ($name === '') {
            return [];
        }

        return [new NodeOutput(name: $name, control: true, match: $name)];
    }

    /**
     * @return list<NodeOutput>
     */
    private function fallthrough(mixed $value): array
    {
        $name = is_string($value) ? $value : 'else';

        return [new NodeOutput(name: $name, control: true, match: null)];
    }
}
