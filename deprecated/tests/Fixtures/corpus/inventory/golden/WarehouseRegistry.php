<?php

namespace App\Inventory;

/**
 * Keyed store of the warehouses the application can allocate stock from.
 */
final class WarehouseRegistry
{
    /**
     * @var array<string, Warehouse>
     */
    private array $warehouses = [];

    public function register(string $key, Warehouse $warehouse): void
    {
        $this->warehouses[$key] = $warehouse;
    }

    public function has(string $key): bool
    {
        return isset($this->warehouses[$key]);
    }

    public function get(string $key): Warehouse
    {
        return $this->warehouses[$key] ?? throw WarehouseNotFoundException::forKey($key);
    }

    /**
     * @return list<Warehouse>
     */
    public function all(): array
    {
        return array_values($this->warehouses);
    }
}
