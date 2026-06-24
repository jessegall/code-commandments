<?php

namespace App\Orders;

use Illuminate\Support\Collection;

/**
 * Loads orders from persistence; a miss is a named exception, a list is a collection.
 */
final class OrderRepository
{
    public function __construct(
        private readonly OrderStore $store,
    ) {}

    public function save(Order $order): void
    {
        $this->store->persist($order);
    }

    public function get(string $id): Order
    {
        return $this->store->find($id) ?? throw OrderNotFoundException::forId($id);
    }

    /**
     * @return Collection<int, Order>
     */
    public function forCustomer(string $customerId): Collection
    {
        return $this->store->forCustomer($customerId);
    }
}
