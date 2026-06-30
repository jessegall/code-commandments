<?php

namespace Shop\Catalog;

use JesseGall\CodeCommandments\Sins\Backend\EnumValueMatch;

use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Models\Product;

/**
 * Picks a badge colour by re-matching the category enum's scalar at the call
 * site — a method that belongs ON ProductCategory.
 */
final class StockBadge
{
    #[Sinful(EnumValueMatch::class)]
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

    /**
     * The mapping lives ON the enum; the call site just asks for the colour.
     */
    #[Righteous(EnumValueMatch::class)]
    public function colourViaEnum(Product $product): string
    {
        return $product->category->badgeColour();
    }

    public function inStockLabel(Product $product): string
    {
        return $product->stock > 0 ? 'In stock' : 'Sold out';
    }
}
