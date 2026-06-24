<?php

namespace App\FeatureEnvy\OrderStatus;

/**
 * Renders an order's lifecycle state by deferring to the status it owns.
 */
final class OrderStatusPresenter
{
    public function label(Order $order): string
    {
        return $order->status()->label();
    }

    public function badgeColor(Order $order): string
    {
        return $order->status()->color();
    }

    public function isOpen(Order $order): bool
    {
        return $order->status()->isOpen();
    }
}
