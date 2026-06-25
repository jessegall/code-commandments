<?php

namespace App\Access;

/**
 * Holds permissions in an array. Keyed by name so we can look them up.
 */
class PermissionSet
{
    public $items = [];

    public function add($name, $data = [])
    {
        $this->items[$name] = $data;
    }

    /**
     * @return array<string,mixed>|null
     */
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

    public function count()
    {
        return (int) (count($this->items) ?? 0);
    }
}
