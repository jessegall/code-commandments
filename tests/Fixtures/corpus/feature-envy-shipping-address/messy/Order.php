<?php

namespace App\FeatureEnvy\ShippingAddress;

/**
 * An order placed by a customer, holding the customer it belongs to.
 */
final readonly class Order
{
    public function __construct(
        private string $id,
        private Customer $customer,
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }
}
