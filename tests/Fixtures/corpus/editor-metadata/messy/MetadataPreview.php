<?php

namespace App\EditorMetadata;

/**
 * Builds the live editor preview blurb for a pending metadata patch.
 *
 * SYMPTOM: a SECOND consumer of the same `mixed`, with its OWN parallel coercion
 * ladder over the aspect string — drifted from the handler's (this one reads
 * 'title' where the handler reads 'label', and treats 'branches' as a flat
 * count). The branches arm re-does the string-or-array ValueBag dance a third
 * time. Two ladders to keep in sync by hand, both coping with one untyped field.
 */
class MetadataPreview
{
    public function summarize(SetNodeMetadataPayload $payload): string
    {
        $value = $payload->value;

        return match ($payload->aspect) {
            'label', 'title' => is_string($value) ? $value : '(no label)',
            'branches' => $this->describeBranches($value),
            'specs' => is_array($value) ? count($value) . ' specs' : '0 specs',
            'match' => is_string($value) ? "match → {$value}" : 'no match',
            default => 'updated',
        };
    }

    private function describeBranches(mixed $value): string
    {
        $branches = is_array($value) ? $value : ($value === null ? [] : [$value]);

        $names = [];

        foreach ($branches as $branch) {
            // the coalesce dance, copied yet again and drifted ('name' first here)
            if (is_array($branch)) {
                $names[] = (string) ($branch['name'] ?? $branch['label'] ?? '?');
            } elseif (is_string($branch)) {
                $names[] = $branch;
            }
        }

        return $names === [] ? 'no branches' : implode(', ', $names);
    }
}
