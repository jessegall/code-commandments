<?php

namespace Shop\Catalog;

use JesseGall\CodeCommandments\Sins\Backend\DeNulledFinder;

use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Models\Product;

/**
 * Looks a product up by barcode and hands back null on a miss — and its own
 * caller immediately de-nulls it, so the absence should live in the type
 * (resolve-or-throw if a scanned barcode must exist).
 */
final class ProductFinder
{
    #[Sinful(DeNulledFinder::class)]
    public function byBarcode(string $barcode): ?Product
    {
        return Product::query()->where('barcode', $barcode)->first();
    }

    public function nameFor(string $barcode): string
    {
        return $this->byBarcode($barcode)?->name ?? 'Unknown product';
    }

    public function inStock(string $barcode): bool
    {
        return $this->byBarcode($barcode) !== null;
    }

    /**
     * Resolve-or-throw: a scanned barcode must exist, so the absence is decided
     * once at the source and the return type tells the truth.
     */
    #[Righteous(DeNulledFinder::class)]
    public function requireByBarcode(string $barcode): Product
    {
        return Product::query()->where('barcode', $barcode)->first()
            ?? throw ProductNotFound::forBarcode($barcode);
    }
}
