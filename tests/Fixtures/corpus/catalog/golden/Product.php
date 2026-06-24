<?php

declare(strict_types=1);

namespace App\Catalog;

/**
 * A single catalog product with its type, price, and owning category.
 */
final readonly class Product
{
    public function __construct(
        public string $sku,
        public string $name,
        public ProductType $type,
        public Money $price,
        public Category $category,
    ) {}

    public function priceFor(int $quantity): Money
    {
        return $this->price->multipliedBy($quantity);
    }
}
