<?php

namespace App\Orders;

use Illuminate\Support\Collection;

/**
 * Loads orders from storage; a miss is a named exception, a list is a collection.
 */
final class OrderRepository
{
    /**
     * @var Collection<string, Order>
     */
    private Collection $orders;

    public function __construct()
    {
        $this->orders = collect();
    }

    public function save(Order $order): void
    {
        $this->orders->put($order->id, $order);
    }

    public function get(string $id): Order
    {
        return $this->orders->get($id) ?? throw OrderNotFoundException::forId($id);
    }

    /**
     * @return Collection<int, Order>
     */
    public function forCustomer(string $customerId): Collection
    {
        return $this->orders
            ->filter(static fn (Order $order): bool => $order->customerId === $customerId)
            ->values();
    }
}
