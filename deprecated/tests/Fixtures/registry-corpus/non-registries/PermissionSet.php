<?php

namespace App\RegistryCorpus;

/**
 * REGISTRY: no — a membership Set: keys flag presence only, there is no value
 * stored or looked up by key, so it never answers "give me the thing at K".
 */
class PermissionSet
{
    /** @var array<string, true> */
    private array $items = [];

    public function add(string $perm): void
    {
        $this->items[$perm] = true;
    }

    public function has(string $perm): bool
    {
        return isset($this->items[$perm]);
    }

    public function remove(string $perm): void
    {
        unset($this->items[$perm]);
    }

    /** @return list<string> */
    public function all(): array
    {
        return array_keys($this->items);
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }
}
