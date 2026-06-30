<?php

namespace Shop\Repositories;

use Shop\Exceptions\OrderNotFoundException;
use Shop\Models\Order;

/**
 * Reads orders through query methods.
 */
final class OrderRepository
{
    public function findOrFail(int $id): Order
    {
        return Order::query()->find($id) ?? throw OrderNotFoundException::forId($id);
    }

    /**
     * @return array<int, Order>
     */
    public function paidForCustomer(int $customerId): array
    {
        return Order::query()->forCustomer($customerId)->paid()->get()->all();
    }
}
