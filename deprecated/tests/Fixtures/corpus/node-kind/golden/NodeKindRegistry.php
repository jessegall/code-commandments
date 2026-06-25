<?php

namespace App\NodeKind;

use Illuminate\Support\Fluent;

/**
 * The single place a node-kind string is resolved into a typed {@see NodeKind}.
 * Every downstream consumer (factory, renderer, validator) reads behaviour off
 * the resolved kind instead of re-deriving it from the raw string.
 */
final class NodeKindRegistry
{
    /**
     * @var array<string, NodeKind>
     */
    private array $kinds = [];

    public function __construct()
    {
        foreach ([new TriggerKind(), new ActionKind(), new ConditionKind()] as $kind) {
            $this->kinds[$kind->key()] = $kind;
        }
    }

    public function has(string $key): bool
    {
        return isset($this->kinds[$key]);
    }

    public function get(string $key): NodeKind
    {
        return $this->kinds[$key] ?? throw NodeKindNotFoundException::forKey($key);
    }

    /**
     * Normalise a node payload — either a bare kind string or a `{kind: …}` bag —
     * into its typed kind, ONCE, at the boundary.
     */
    public function classify(Fluent $node): NodeKind
    {
        return $this->get($node->string('kind'));
    }
}
