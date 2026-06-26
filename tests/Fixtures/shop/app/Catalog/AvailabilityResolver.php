<?php

namespace Shop\Catalog;

use JesseGall\CodeCommandments\Detectors\Backend\RedundantElseDetector;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Models\Product;

/**
 * Resolves availability through a loop where the matched branch `continue`s, so
 * the `else` is redundant.
 *
 * @return array<int, Product>
 */
final class AvailabilityResolver
{
    /**
     * @param  array<int, Product>  $products
     * @return array<int, Product>
     */
    #[Sinful(RedundantElseDetector::class)]
    public function inStock(array $products): array
    {
        $available = [];

        foreach ($products as $product) {
            if ($product->stock <= 0) {
                continue;
            } else {
                $available[] = $product;
            }
        }

        return $available;
    }
}
