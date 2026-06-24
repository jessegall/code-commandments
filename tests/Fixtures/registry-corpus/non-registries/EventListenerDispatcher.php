<?php

namespace App\RegistryCorpus;

use Illuminate\Support\Collection;

/**
 * REGISTRY: no — keyed APPEND-and-iterate multimap (an event dispatcher), not register-one + get-one-by-key.
 *
 * Borderline because it IS keyed: listen() puts callbacks under an event name and they
 * are stored in $this->listeners[$event]. But the shape is a multimap — each key holds a
 * GROWING LIST you append to (listeners[$event][] = ...) and then ITERATE over in dispatch(),
 * never a single value you register once and look up by key. There is no get($key): T lookup
 * returning one stored thing; the "read" is a fan-out loop. That append-and-broadcast shape
 * is what separates a dispatcher from a registry.
 */
class EventListenerDispatcher
{
    /** @var array<string, list<callable>> */
    private array $listeners = [];

    public function listen(string $event, callable $callback): void
    {
        $this->listeners[$event][] = $callback;
    }

    public function dispatch(string $event, mixed ...$payload): Collection
    {
        return Collection::make($this->listeners[$event] ?? [])
            ->map(fn (callable $listener) => $listener(...$payload));
    }
}
