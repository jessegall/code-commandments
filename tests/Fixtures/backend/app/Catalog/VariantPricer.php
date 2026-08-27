<?php

namespace Shop\Catalog;

use JesseGall\CodeCommandments\Sins\Backend\ParamResolvedFromParam;

use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Digs the variant out of the catalogue by sku just to price it. The catalogue is
 * never used for anything else — the caller already has it and the sku, so it
 * should hand over the resolved variant.
 */
final class VariantPricer
{
    public function __construct(private readonly int $markupCents = 0) {}

    #[Sinful(ParamResolvedFromParam::class)]
    public function priceFor(ProductCatalogue $catalogue, string $sku): int
    {
        $variant = $catalogue->variantBySku($sku);

        return $variant->basePriceCents() + $this->markupCents;
    }

    /**
     * Demands the resolved variant — the caller resolves it once by sku and owns
     * the "not found" failure, so this only prices what it is handed.
     */
    #[Fixed(ParamResolvedFromParam::class)]
    #[Righteous(ParamResolvedFromParam::class)]
    public function priceForVariant(Variant $variant): int
    {
        return $variant->basePriceCents() + $this->markupCents;
    }
}

final class ProductCatalogue
{
    /**
     * @var array<string, Variant>
     */
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

/**
 * The caller's half of the same fix: it holds the catalogue, so it resolves the variant ONCE and
 * owns the "no such sku" failure — which is exactly the knowledge the pricer was borrowing.
 */
final class CartPricing
{
    public function __construct(
        private readonly ProductCatalogue $catalogue,
        private readonly VariantPricer $pricer,
    ) {}

    #[Fixed(ParamResolvedFromParam::class)]
    public function lineTotal(string $sku): int
    {
        return $this->pricer->priceForVariant($this->catalogue->variantBySku($sku));
    }
}
