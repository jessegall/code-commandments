<?php

namespace Shop\Catalog;

use JesseGall\CodeCommandments\Detectors\Backend\MatchDefaultReturnsNullDetector;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Models\Product;

/**
 * Labels a product's priority — the default arm swallows an unknown level as null
 * instead of failing on a case nobody handled.
 */
final class PriorityLabel
{
    #[Sinful(MatchDefaultReturnsNullDetector::class)]
    public function for(Product $product): ?string
    {
        return match ($product->priority) {
            1 => 'urgent',
            2 => 'normal',
            3 => 'low',
            default => null,
        };
    }
}
