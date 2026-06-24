<?php

namespace App\RegistryCorpus;

use Illuminate\Support\Fluent;

/**
 * REGISTRY: yes — register() puts keyed values into a held Fluent store via ->set(),
 * and resolve() looks them back up by key via ->get(); a keyed put/lookup over a wrapper.
 */
class FluentSettingRegistry
{
    private Fluent $store;

    public function __construct()
    {
        $this->store = new Fluent();
    }

    public function register(string $key, mixed $value): void
    {
        $this->store->set($key, $value);
    }

    public function resolve(string $key, mixed $default = null): mixed
    {
        return $this->store->get($key, $default);
    }

    public function has(string $key): bool
    {
        return $this->store->get($key) !== null;
    }

    public function all(): array
    {
        return $this->store->toArray();
    }
}
