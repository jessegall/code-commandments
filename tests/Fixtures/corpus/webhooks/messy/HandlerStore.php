<?php

namespace App\Webhooks;

class HandlerStore
{
    private array $items = [];

    public function add($key, $handler)
    {
        $this->items[$key] = $handler;
    }

    public function get($key)
    {
        return $this->items[$key] ?? null;
    }
}
