<?php

namespace App\Inventory;

/**
 * Keyed store of warehouses. Junior named it *Store, not *Registry, and the
 * accessor returns null instead of throwing — no has().
 */
class WarehouseStore
{
    /**
     * @var array<string,mixed>
     */
    public $items = [];

    public function register($key, $warehouse)
    {
        $this->items[$key] = $warehouse;
    }

    public function get($key)
    {
        return $this->items[$key] ?? null;
    }

    /**
     * @return array<string,mixed>
     */
    public function all()
    {
        return $this->items;
    }
}
