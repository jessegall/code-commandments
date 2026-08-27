<?php

namespace Shop\Catalog;

use JesseGall\CodeCommandments\Sins\Backend\DeNulledFinder;
use JesseGall\CodeCommandments\Testing\Fixed;
use RuntimeException;

/**
 * The absence the finder used to hand back as null, decided once and named. Every caller that
 * de-nulled the `?Product` is now spared the question.
 */
#[Fixed(DeNulledFinder::class)]
final class ProductNotFound extends RuntimeException
{
    public static function forBarcode(string $barcode): self
    {
        return new self("No product is registered under barcode {$barcode}.");
    }
}
