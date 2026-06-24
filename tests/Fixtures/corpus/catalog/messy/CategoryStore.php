<?php

namespace App\Catalog;

class CategoryStore
{
    /**
     * @var array<string, mixed>
     */
    public $items = [];

    /**
     * @param array<string, mixed> $data
     */
    public function add($key, array $data)
    {
        $this->items[$key] = $data;
    }

    public function get($key)
    {
        return $this->items[$key] ?? null;
    }

    public function getName($key)
    {
        $cat = $this->items[$key] ?? null;

        if ($cat == null) {
            return 'Unknown';
        }

        return $cat['name'] ?? 'Unknown';
    }
}
