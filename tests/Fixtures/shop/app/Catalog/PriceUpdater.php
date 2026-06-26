<?php

namespace Shop\Catalog;

use JesseGall\CodeCommandments\Detectors\Backend\ModelMutationAtCallSiteDetector;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Models\Product;

/**
 * Discounts a product by poking its columns and saving at the call site — the
 * "put it on sale" transition has no home on the model.
 */
final class PriceUpdater
{
    #[Sinful(ModelMutationAtCallSiteDetector::class)]
    public function discount(Product $product, int $percent): void
    {
        $product->price_cents = (int) ($product->price_cents * (100 - $percent) / 100);
        $product->on_sale = true;
        $product->save();
    }
}
