<?php

namespace App\FeatureEnvy\CartDiscount;

/**
 * A customer placing the cart; an anaemic bag of getters.
 */
final readonly class Customer
{
    public function __construct(
        private string $id,
        private string $name,
        private CustomerTier $tier,
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTier(): CustomerTier
    {
        return $this->tier;
    }
}
