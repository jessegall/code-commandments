<?php

namespace Shop\Services;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\NewDataObject;

use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Data\OrderData;
use Shop\Data\OrderLineData;
use Shop\Models\Order;

final class OrderAssembler
{
    #[Sinful(NewDataObject::class)]
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
