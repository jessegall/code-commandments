<?php

namespace Shop\Orders;

use JesseGall\CodeCommandments\Detectors\Backend\RedundantElseDetector;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Models\Order;

/**
 * Decides a refund window — the `if` already returns, so the `else` is dead
 * weight; the happy path should continue unindented.
 */
final class RefundPolicy
{
    #[Sinful(RedundantElseDetector::class)]
    public function window(Order $order): string
    {
        if ($order->status === 'shipped') {
            return '14 days';
        } else {
            return '30 days';
        }
    }
}
