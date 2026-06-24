<?php

namespace App\FeatureEnvy\CartDiscount;

use Illuminate\Support\Collection;

/**
 * A customer's cart; an anaemic bag whose data others reach through.
 */
final readonly class Cart
{
    /**
     * @param Collection<int, CartItem> $items
     */
    public function __construct(
        private string $id,
        private Customer $customer,
        private Collection $items,
        private string $currency,
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    /**
     * @return Collection<int, CartItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }
}
