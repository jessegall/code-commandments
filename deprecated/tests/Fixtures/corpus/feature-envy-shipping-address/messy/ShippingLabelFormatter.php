<?php

namespace App\FeatureEnvy\ShippingAddress;

/**
 * Builds the shipping label text for an order's destination address.
 */
final class ShippingLabelFormatter
{
    public function format(Order $order): string
    {
        $address = $order->getCustomer()->getAddress();

        $street = $address->getStreet();
        $city = $address->getCity();
        $zip = $address->getZip();

        $name = $order->getCustomer()->getName();

        return $name . "\n" . $street . "\n" . $city . ', ' . $zip;
    }

    public function csvRow(Order $order): string
    {
        return implode(',', [
            $order->getCustomer()->getName(),
            $order->getCustomer()->getAddress()->getStreet(),
            $order->getCustomer()->getAddress()->getCity(),
            $order->getCustomer()->getAddress()->getZip(),
        ]);
    }
}
