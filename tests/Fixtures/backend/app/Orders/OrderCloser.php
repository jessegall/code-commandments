<?php

namespace Shop\Orders;

use JesseGall\CodeCommandments\Sins\Backend\Laravel\MassUpdateAtCallSite;

use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Models\Order;

/**
 * Closes an order with a bare array update at the call site — the "close"
 * transition has no name on the model.
 */
final class OrderCloser
{
    #[Sinful(MassUpdateAtCallSite::class)]
    public function close(Order $order, string $reason): void
    {
        $order->update([
            'status' => 'closed',
            'closed_reason' => $reason,
        ]);
    }

    public function isClosable(Order $order): bool
    {
        return $order->status !== 'closed' && $order->total_cents > 0;
    }

    public function closeAll(array $orders, string $reason): int
    {
        $closed = 0;

        foreach ($orders as $order) {
            $this->close($order, $reason);
            $closed++;
        }

        return $closed;
    }
}

