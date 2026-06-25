<?php

namespace App\FeatureEnvy\ShippingAddress;

/**
 * A customer that exposes its shipping address as a ready label block.
 */
final readonly class Customer
{
    public function __construct(
        public string $name,
        public Address $address,
    ) {}

    public function shippingAddress(): string
    {
        return $this->name . ' — ' . $this->address->oneLine();
    }
}
