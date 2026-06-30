<?php

namespace Shop\Catalog;

use JesseGall\CodeCommandments\Sins\Backend\RedundantElse;

use JesseGall\CodeCommandments\Testing\Righteous;
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
    #[Sinful(RedundantElse::class)]
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

    /**
     * The guard handles the absent case and `continue`s; the happy path runs
     * unindented with no redundant `else`.
     *
     * @param  array<int, Product>  $products
     * @return array<int, Product>
     */
    #[Righteous(RedundantElse::class)]
    public function available(array $products): array
    {
        $available = [];

        foreach ($products as $product) {
            if ($product->stock <= 0) {
                continue;
            }

            $available[] = $product;
        }

        return $available;
    }
}
