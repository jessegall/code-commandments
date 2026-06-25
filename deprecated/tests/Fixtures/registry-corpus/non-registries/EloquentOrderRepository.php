<?php

namespace App\RegistryCorpus;

use App\Models\Order;
use Illuminate\Support\Collection;

/**
 * REGISTRY: no — a repository: lookups hit the database (Order::find) rather than
 * an in-memory keyed store you register entries into, so there is no put/get map.
 */
class EloquentOrderRepository
{
    public function find(int $id): ?Order
    {
        return Order::query()->find($id);
    }

    public function pending(): Collection
    {
        return Order::query()->where('status', 'pending')->get();
    }

    public function save(Order $order): void
    {
        $order->save();
    }

    public function delete(Order $order): void
    {
        $order->delete();
    }
}
