<?php

namespace App\FeatureEnvy\ShippingAddress;

/**
 * A customer: a name and the address their orders ship to.
 */
final readonly class Customer
{
    public function __construct(
        private string $name,
        private Address $address,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getAddress(): Address
    {
        return $this->address;
    }
}
