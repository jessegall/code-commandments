<?php

namespace Shop\Data;

use Shop\Enums\ProductCategory;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

/**
 * Typed view of a product. The `#[WithCast]` on `category` is work `::from()` runs
 * and a raw `new` skips — so this class is RICH: `new ProductData(...)` is a real sin.
 */
final class ProductData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly int $priceCents,
        #[WithCast(EnumCast::class)]
        public readonly ProductCategory $category,
    ) {}
}
