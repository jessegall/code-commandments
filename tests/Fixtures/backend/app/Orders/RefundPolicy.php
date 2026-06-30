<?php

namespace Shop\Orders;

use JesseGall\CodeCommandments\Sins\Backend\RedundantElse;

use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Models\Order;

/**
 * Decides a refund window — the `if` already returns, so the `else` is dead
 * weight; the happy path should continue unindented.
 */
final class RefundPolicy
{
    #[Sinful(RedundantElse::class)]
    public function window(Order $order): string
    {
        if ($order->status === 'shipped') {
            return '14 days';
        } else {
            return '30 days';
        }
    }
}
