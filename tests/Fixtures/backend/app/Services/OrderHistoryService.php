<?php

namespace Shop\Services;

use Shop\Models\Customer;
use Shop\Models\Order;
use Shop\Repositories\OrderRepository;
use Shop\ValueObjects\Money;

/**
 * Reads a customer's order history.
 */
final class OrderHistoryService
{
    public function __construct(private readonly OrderRepository $orders) {}

    /**
     * @return array<int, Order>
     */
    public function forCustomer(Customer $customer): array
    {
        return $this->orders->paidForCustomer($customer->id);
    }

    public function totalSpent(Customer $customer): Money
    {
        $total = Money::ofCents(0);

        foreach ($this->forCustomer($customer) as $order) {
            $total = $total->add($order->total());
        }

        return $total;
    }
}
