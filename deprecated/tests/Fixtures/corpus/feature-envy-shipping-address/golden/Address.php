<?php

namespace App\FeatureEnvy\ShippingAddress;

/**
 * A postal address that knows how to render itself as one line.
 */
final readonly class Address
{
    public function __construct(
        public string $street,
        public string $city,
        public string $zip,
    ) {}

    public function oneLine(): string
    {
        return $this->street . ', ' . $this->city . ', ' . $this->zip;
    }
}
