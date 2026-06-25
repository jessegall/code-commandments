<?php

namespace App\Orders;

use Illuminate\Support\Collection;

/**
 * The persistence port the OrderRepository loads orders through.
 */
interface OrderStore
{
    public function persist(Order $order): void;

    public function find(string $id): ?Order;

    /**
     * @return Collection<int, Order>
     */
    public function forCustomer(string $customerId): Collection;
}
