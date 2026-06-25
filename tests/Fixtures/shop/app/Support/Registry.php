<?php

namespace Shop\Support;

use Shop\Exceptions\RegistryEntryNotFoundException;

abstract class Registry
{
    /**
     * @var array<string, mixed>
     */
    private array $items = [];

    public function register(string $key, mixed $item): static
    {
        $this->items[$key] = $item;

        return $this;
    }

    public function has(string $key): bool
    {
        return isset($this->items[$key]);
    }

    public function get(string $key): mixed
    {
        return $this->items[$key] ?? throw RegistryEntryNotFoundException::forKey($key);
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->items;
    }
}
