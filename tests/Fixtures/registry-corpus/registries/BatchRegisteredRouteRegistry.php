<?php

namespace App\RegistryCorpus;

/**
 * REGISTRY: yes — you batch-register named routes INTO a keyed store
 * and later LOOK them up by name; classic put-keyed / get-keyed registry.
 */
class BatchRegisteredRouteRegistry
{
    /** @var array<string, string> */
    private array $routes = [];

    /**
     * @param array<string, string> $routes name => uri
     */
    public function registerMany(array $routes): void
    {
        foreach ($routes as $name => $uri) {
            $this->routes = array_merge($this->routes, [$name => $uri]);
        }
    }

    public function get(string $name): ?string
    {
        return $this->routes[$name] ?? null;
    }

    public function has(string $name): bool
    {
        return isset($this->routes[$name]);
    }
}
