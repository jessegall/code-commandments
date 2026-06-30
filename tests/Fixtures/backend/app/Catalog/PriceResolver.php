<?php

namespace Shop\Catalog;

use JesseGall\CodeCommandments\Detectors\Backend\DeepNestingDetector;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Models\Product;

/**
 * Resolves a price override buried under a pyramid of conditions.
 */
final class PriceResolver
{
    /**
     * @param  array<string, int>  $overrides
     */
    #[Sinful(DeepNestingDetector::class)]
    public function resolve(Product $product, array $overrides, string $region): int
    {
        if (array_key_exists($region, $overrides)) {
            if ($product->price_cents > 0) {
                if ($overrides[$region] < $product->price_cents) {
                    return $overrides[$region];
                }
            }
        }

        return $product->price_cents;
    }

    public function isDiscounted(Product $product, int $resolved): bool
    {
        if ($resolved < $product->price_cents) {
            if ($product->price_cents > 0) {
                return true;
            }
        }

        return false;
    }
}
