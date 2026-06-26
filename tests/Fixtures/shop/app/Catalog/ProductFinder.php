<?php

namespace Shop\Catalog;

use JesseGall\CodeCommandments\Detectors\Backend\DeNulledFinderDetector;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Models\Product;

/**
 * Looks a product up by barcode and hands back null on a miss — and its own
 * caller immediately de-nulls it, so the absence should live in the type
 * (resolve-or-throw if a scanned barcode must exist).
 */
final class ProductFinder
{
    #[Sinful(DeNulledFinderDetector::class)]
    public function byBarcode(string $barcode): ?Product
    {
        return Product::query()->where('barcode', $barcode)->first();
    }

    public function nameFor(string $barcode): string
    {
        return $this->byBarcode($barcode)?->name ?? 'Unknown product';
    }
}
