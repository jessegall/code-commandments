<?php

namespace App\RegistryCorpus;

use Illuminate\Support\Collection;

/**
 * REGISTRY: yes — keyed drivers are PUT in via register() and LOOKED UP via get(),
 * backed by a Collection store mutated through put()/get() (method-based, not array writes).
 */
class CollectionDriverRegistry
{
    /** @var Collection<string, object> */
    private Collection $drivers;

    public function __construct()
    {
        $this->drivers = new Collection();
    }

    public function register(string $key, object $driver): void
    {
        $this->drivers->put($key, $driver);
    }

    public function get(string $key): ?object
    {
        return $this->drivers->get($key);
    }

    public function has(string $key): bool
    {
        return $this->drivers->has($key);
    }
}
