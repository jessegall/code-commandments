<?php

namespace Shop\Services;

use Shop\Data\OrderData;
use Shop\Data\OrderLineData;
use Shop\Models\Order;

final class OrderAssembler
{
    public function assemble(Order $order): OrderData
    {
        $lines = [];

        foreach ($order->lines as $line) {
            $lines[] = new OrderLineData(
                productId: $line->product_id,
                quantity: $line->quantity,
                priceCents: $line->price_cents,
            );
        }

        return new OrderData(
            id: $order->id,
            status: $order->status,
            totalCents: $order->total_cents,
            lines: $lines,
        );
    }
}
