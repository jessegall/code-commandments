<?php

namespace App\RegistryCorpus;

use WeakMap;

/**
 * REGISTRY: yes — you register a handler against an arbitrary object key
 * and later look it up by that same object; the WeakMap IS the keyed store.
 */
class WeakMapHandlerRegistry
{
    /** @var WeakMap<object, callable> */
    private WeakMap $map;

    public function __construct()
    {
        $this->map = new WeakMap();
    }

    public function register(object $key, callable $handler): void
    {
        $this->map[$key] = $handler;
    }

    public function get(object $key): ?callable
    {
        return $this->map[$key] ?? null;
    }

    public function has(object $key): bool
    {
        return isset($this->map[$key]);
    }
}
