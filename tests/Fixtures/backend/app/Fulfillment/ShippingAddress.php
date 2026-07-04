<?php

namespace Shop\Fulfillment;

/**
 * Where an order ships to.
 */
final class ShippingAddress
{
    public function __construct(private readonly string $postal) {}

    public function postalCode(): string
    {
        return $this->postal;
    }
}
