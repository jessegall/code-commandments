<?php

namespace Shop\Api;

use Spatie\LaravelData\Data;

/**
 * The product payload. A `type ProductData = { … }` in the frontend that restates these
 * fields is the duplicated contract this sin flags.
 */
final class ProductData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly int $price,
        public readonly string $sku,
        public readonly int $stock,
    ) {}
}
