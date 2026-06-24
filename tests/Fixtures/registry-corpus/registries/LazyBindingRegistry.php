<?php

namespace App\RegistryCorpus;

use Closure;
use InvalidArgumentException;

/**
 * REGISTRY: yes — you bind keyed factory closures into it and resolve keyed
 * instances back out; deferred construction doesn't change the put/lookup shape.
 */
class LazyBindingRegistry
{
    /** @var array<string, Closure> */
    private array $bindings = [];

    public function register(string $key, Closure $factory): void
    {
        $this->bindings[$key] = $factory;
    }

    public function resolve(string $key): object
    {
        $factory = $this->bindings[$key]
            ?? throw new InvalidArgumentException("Nothing bound for [{$key}].");

        return $factory();
    }

    public function has(string $key): bool
    {
        return isset($this->bindings[$key]);
    }
}
