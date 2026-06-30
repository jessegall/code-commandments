<?php

namespace Shop\Catalog;

use JesseGall\CodeCommandments\Sins\Backend\FeatureEnvy;
use JesseGall\CodeCommandments\Sins\Backend\ModelMutationAtCallSite;

use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Models\Product;

/**
 * Applies a percentage markdown straight to a Product's fields and persists it.
 * The sale rule is stranded in a service instead of living where the data does.
 */
final class PriceUpdater
{
    public function __construct(private readonly int $floorCents = 99) {}

    #[Sinful(ModelMutationAtCallSite::class)]
    #[Sinful(FeatureEnvy::class)]
    public function discount(Product $product, int $percent): void
    {
        $marked = (int) ($product->price_cents * (100 - $percent) / 100);
        $product->price_cents = max($this->floorCents, $marked);
        $product->on_sale = true;
        $product->save();
    }
}
