<?php

namespace Shop\Catalog;

use JesseGall\CodeCommandments\Sins\Backend\EnumValueMatch;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Enums\ProductCategory;
use Shop\Models\Product;

/**
 * Picks an aisle by holding the category's scalar in a local and matching it against each case's value again.
 */
final class ShelfPlacement
{
    #[Sinful(EnumValueMatch::class)]
    public function aisle(Product $product): int
    {
        $category = $product->category->value;

        return match ($category) {
            ProductCategory::Food->value => 1,
            ProductCategory::Electronics->value => 4,
            default => 9,
        };
    }

    #[Righteous(EnumValueMatch::class)]
    public function shelfHeight(string $size): int
    {
        return match ($size) {
            'small' => 1,
            'large' => 3,
            default => 2,
        };
    }
}
