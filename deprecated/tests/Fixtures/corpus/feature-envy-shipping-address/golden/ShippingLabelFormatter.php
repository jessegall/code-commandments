<?php

namespace App\FeatureEnvy\ShippingAddress;

/**
 * Builds the shipping label text by asking the order for its own label.
 */
final class ShippingLabelFormatter
{
    public function format(Order $order): string
    {
        return $order->shippingLabel();
    }
}
