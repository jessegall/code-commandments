<?php

namespace App\FeatureEnvy\ShippingAddress;

/**
 * An order that can hand back the shipping label block for its destination.
 */
final readonly class Order
{
    public function __construct(
        public string $id,
        public Customer $customer,
    ) {}

    public function shippingLabel(): string
    {
        return $this->customer->shippingAddress();
    }
}
