<?php

namespace Shop\Catalog;

use JesseGall\CodeCommandments\Detectors\Backend\ParamResolvedFromParamDetector;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Digs the variant out of the catalogue by sku just to price it. The catalogue is
 * never used for anything else — the caller already has it and the sku, so it
 * should hand over the resolved variant.
 */
final class VariantPricer
{
    public function __construct(private readonly int $markupCents = 0) {}

    #[Sinful(ParamResolvedFromParamDetector::class)]
    public function priceFor(ProductCatalogue $catalogue, string $sku): int
    {
        $variant = $catalogue->variantBySku($sku);

        return $variant->basePriceCents() + $this->markupCents;
    }
}

final class ProductCatalogue
{
    /** @var array<string, Variant> */
    public array $variants = [];

    public function variantBySku(string $sku): Variant
    {
        return $this->variants[$sku];
    }
}

final class Variant
{
    public function basePriceCents(): int
    {
        return 999;
    }
}
