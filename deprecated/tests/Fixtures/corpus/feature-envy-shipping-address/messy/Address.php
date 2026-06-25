<?php

namespace App\FeatureEnvy\ShippingAddress;

/**
 * A postal address: street, city and zip — a passive bag of getters here.
 */
final readonly class Address
{
    public function __construct(
        private string $street,
        private string $city,
        private string $zip,
    ) {}

    public function getStreet(): string
    {
        return $this->street;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function getZip(): string
    {
        return $this->zip;
    }
}
