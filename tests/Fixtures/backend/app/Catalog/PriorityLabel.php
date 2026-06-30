<?php

namespace Shop\Catalog;

use JesseGall\CodeCommandments\Sins\Backend\MatchDefaultReturnsNull;

use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Models\Product;

/**
 * Labels a product's priority — the default arm swallows an unknown level as null
 * instead of failing on a case nobody handled.
 */
final class PriorityLabel
{
    #[Sinful(MatchDefaultReturnsNull::class)]
    public function for(Product $product): ?string
    {
        return match ($product->priority) {
            1 => 'urgent',
            2 => 'normal',
            3 => 'low',
            default => null,
        };
    }

    /**
     * The default arm throws a named exception, so an unhandled priority fails
     * loudly instead of being swallowed into null.
     */
    #[Righteous(MatchDefaultReturnsNull::class)]
    public function strictFor(Product $product): string
    {
        return match ($product->priority) {
            1 => 'urgent',
            2 => 'normal',
            3 => 'low',
            default => throw UnknownPriority::for($product->priority),
        };
    }
}
