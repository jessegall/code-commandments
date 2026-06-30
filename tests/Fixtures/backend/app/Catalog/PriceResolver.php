<?php

namespace Shop\Catalog;

use JesseGall\CodeCommandments\Sins\Backend\DeepNesting;

use JesseGall\CodeCommandments\Testing\Righteous;
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
    #[Sinful(DeepNesting::class)]
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

    /**
     * The same resolution flattened: preconditions become guard clauses, so the
     * happy path runs unindented at the top level.
     *
     * @param  array<string, int>  $overrides
     */
    #[Righteous(DeepNesting::class)]
    public function resolveFlat(Product $product, array $overrides, string $region): int
    {
        if (! array_key_exists($region, $overrides)) {
            return $product->price_cents;
        }

        if ($product->price_cents <= 0) {
            return $product->price_cents;
        }

        return min($overrides[$region], $product->price_cents);
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
