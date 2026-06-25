<?php

namespace App\NodeKind;

use Illuminate\Support\Fluent;

/**
 * Renders the canvas glyph + caption for a node — a SECOND match ladder over the
 * same kind string, drifted from the factory's label arm.
 */
class NodeRenderer
{
    public function glyph(Fluent $node): string
    {
        $kind = $node->get('kind') ?? 'action';

        return match ($kind) {
            'trigger' => '⚡',
            'action' => '▶',
            'condition' => '◆',
            default => '•',
        };
    }

    public function caption(Fluent $node): string
    {
        $kind = (string) $node->get('kind');

        // drifted copy of the factory's label arm
        $label = match ($kind) {
            'trigger' => 'Trigger',
            'action' => 'Action',
            'condition' => 'Condition',
            default => 'Unknown',
        };

        return $label . ': ' . ($node->get('label') ?? '');
    }
}
