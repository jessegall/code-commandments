<?php

namespace Shop\Services;

use Shop\Models\Order;

final class RefundService
{
    public function refund(Order $order, int $amountCents): void
    {
        if (! $order->status->isTerminal()) {
            return;
        }

        $order->markRefunded($amountCents);
    }

    public function reasonFor(Order $order): string
    {
        return strtoupper($order->refund_reason ?? throw new \LogicException('A refunded order must carry a reason.'));
    }
}
