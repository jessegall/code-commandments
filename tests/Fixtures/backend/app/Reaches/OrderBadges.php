<?php

namespace Shop\Reaches;

use JesseGall\CodeCommandments\Testing\Righteous;
use Shop\Models\Order;

#[Righteous(RepeatedReach::class)]
final class OrderBadges
{
    public function label(Order $order): string
    {
        return $order->status->label();
    }

    public function isClosed(Order $order): bool
    {
        return $order->status->isTerminal();
    }
}
