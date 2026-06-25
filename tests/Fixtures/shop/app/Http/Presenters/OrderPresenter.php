<?php

namespace Shop\Http\Presenters;

use Shop\Data\OrderData;
use Shop\Models\Order;

final class OrderPresenter
{
    public function present(Order $order): OrderData
    {
        return OrderData::from($order);
    }

    public function legacyPresent(Order $order): OrderData
    {
        return new OrderData(
            id: $order->id,
            status: $order->status,
            totalCents: $order->total_cents,
            lines: [],
        );
    }

    public function label(Order $order): string
    {
        return $order->status->label();
    }

    public function badge(Order $order): string
    {
        return match ($order->status->value) {
            'pending' => 'grey',
            'paid' => 'green',
            'shipped' => 'blue',
            'cancelled' => 'red',
            default => 'grey',
        };
    }
}
