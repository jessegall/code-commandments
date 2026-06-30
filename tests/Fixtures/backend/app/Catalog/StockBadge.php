<?php

namespace Shop\Catalog;

use JesseGall\CodeCommandments\Detectors\Backend\EnumValueMatchDetector;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Models\Product;

/**
 * Picks a badge colour by re-matching the category enum's scalar at the call
 * site — a method that belongs ON ProductCategory.
 */
final class StockBadge
{
    #[Sinful(EnumValueMatchDetector::class)]
    public function colour(Product $product): string
    {
        switch ($product->category->value) {
            case 'food':
                return 'green';
            case 'electronics':
                return 'blue';
            case 'clothing':
                return 'purple';
            default:
                return 'grey';
        }
    }

    public function inStockLabel(Product $product): string
    {
        return $product->stock > 0 ? 'In stock' : 'Sold out';
    }
}
