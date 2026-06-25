<?php

namespace App\FeatureEnvy\OrderStatus;

/**
 * Renders a human label and a badge colour for an order's lifecycle state.
 */
final class OrderStatusPresenter
{
    public function label(Order $order): string
    {
        if ($order->getCancelledAt() !== null) {
            return 'Cancelled';
        }

        if ($order->getDeliveredAt() !== null) {
            return 'Delivered';
        }

        if ($order->getShippedAt() !== null) {
            return 'Shipped';
        }

        if ($order->getPaidAt() !== null) {
            return 'Paid';
        }

        return 'Pending';
    }

    public function badgeColor(Order $order): string
    {
        if ($order->getCancelledAt() !== null) {
            return 'red';
        }

        if ($order->getDeliveredAt() !== null) {
            return 'green';
        }

        if ($order->getShippedAt() !== null) {
            return 'blue';
        }

        if ($order->getPaidAt() !== null) {
            return 'amber';
        }

        return 'gray';
    }

    public function isOpen(Order $order): bool
    {
        return $order->getCancelledAt() === null
            && $order->getDeliveredAt() === null;
    }
}
