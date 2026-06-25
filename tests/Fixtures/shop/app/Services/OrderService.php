<?php

namespace Shop\Services;

use Shop\Exceptions\OrderNotFoundException;
use Shop\Models\Order;

final class OrderService
{
    public function __construct(private readonly PaymentProcessor $payments) {}

    /**
     * @param  array<int, mixed>  $lines
     */
    public function place(int $customerId, array $lines): Order
    {
        $order = new Order(['customer_id' => $customerId, 'status' => 'pending']);
        $order->save();

        return $order;
    }

    public function find(int $id): Order
    {
        return Order::query()->find($id) ?? throw OrderNotFoundException::forId($id);
    }

    public function settle(Order $order): void
    {
        $this->payments->capture($order->total()->cents);
        $order->markPaid();
    }
}
