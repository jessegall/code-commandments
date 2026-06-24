<?php

namespace App\NodeKind;

use Illuminate\Support\Fluent;

/**
 * Builds a node bag from a raw kind string — the FIRST of three parallel match
 * ladders that hard-code the trigger/action/condition arms and drift apart.
 */
class NodeFactory
{
    public function make(string $kind): Fluent
    {
        $label = match ($kind) {
            'trigger' => 'When this happens',
            'action' => 'Do this',
            'condition' => 'Only if',
            default => 'Node',
        };

        return new Fluent([
            'kind' => $kind,
            'label' => $label,
            'config' => [],
        ]);
    }

    public function defaultConfigKey(string $kind): string
    {
        // yet another arm of the same ladder, drifted: 'expr' vs 'expression'
        return match ($kind) {
            'trigger' => 'event',
            'action' => 'tool',
            'condition' => 'expr',
            default => '',
        };
    }
}
