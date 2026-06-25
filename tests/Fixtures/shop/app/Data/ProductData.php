<?php

namespace Shop\Data;

use Shop\Enums\ProductCategory;
use Spatie\LaravelData\Data;

/**
 * Typed view of a product.
 */
final class ProductData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly int $priceCents,
        public readonly ProductCategory $category,
    ) {}
}
