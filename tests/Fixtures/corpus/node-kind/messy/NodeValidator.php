<?php

namespace App\NodeKind;

use Illuminate\Support\Fluent;

/**
 * Checks a node carries the config its kind needs — a THIRD match ladder that
 * already drifted from the factory (factory seeds `expr`, this reads `expression`).
 */
class NodeValidator
{
    /**
     * @return list<string>
     */
    public function missingConfig(Fluent $node): array
    {
        $kind = (string) $node->get('kind');
        $config = $node->get('config') ?? [];

        $required = match ($kind) {
            'trigger' => 'event',
            'action' => 'tool',
            'condition' => 'expression',
            default => null,
        };

        if ($required === null) {
            return [];
        }

        return empty($config[$required]) ? [$required] : [];
    }

    public function isKnownKind(string $kind): bool
    {
        // literal string compares — the fourth copy of the arm list
        return $kind === 'trigger'
            || $kind === 'action'
            || $kind === 'condition';
    }
}
