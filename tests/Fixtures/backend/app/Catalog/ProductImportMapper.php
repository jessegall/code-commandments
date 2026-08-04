<?php

namespace Shop\Catalog;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\ManualHydrationLoop;

use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Data\ProductData;

/**
 * Maps rows to ProductData with `::from()` in a foreach — the per-item mapping
 * Spatie's `::collect()` does in one pass.
 */
final class ProductImportMapper
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, ProductData>
     */
    #[Sinful(ManualHydrationLoop::class)]
    public function map(array $rows): array
    {
        $products = [];

        foreach ($rows as $row) {
            $products[] = ProductData::from($row);
        }

        return $products;
    }

    public function mapOne(array $row): ProductData
    {
        return ProductData::from($row);
    }

    /**
     * The FIX for {@see map()}: one pass, no loop — `::collect()` maps every row itself, and the
     * element type is declared once on the receiving `#[DataCollectionOf(ProductData::class)]` slot.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    #[Fixed(ManualHydrationLoop::class)]
    public function collectAll(array $rows): mixed
    {
        return ProductData::collect($rows);
    }

    /**
     * A TOLERANT decoder — each entry is decoded in its own try/catch and a malformed
     * row is skipped, not fatal. `::collect()` is all-or-nothing (one bad row throws),
     * so it cannot express the skip — NOT this sin.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, ProductData>
     */
    public function tolerant(array $rows): array
    {
        $products = [];

        foreach ($rows as $row) {
            try {
                $products[] = ProductData::from($row);
            } catch (\Throwable $e) {
                continue;
            }
        }

        return $products;
    }

    /**
     * Builds a KEYED map — keyed by a computed sku, merged into each item. `::collect()`
     * returns a LIST and cannot key by a computed value, so this is NOT the one-pass
     * mapping the skill replaces.
     *
     * @param  array<string, array<string, mixed>>  $entries
     * @return array<string, ProductData>
     */
    public function keyedBySku(array $entries): array
    {
        $catalog = [];

        foreach ($entries as $sku => $entry) {
            $catalog[$sku] = ProductData::from([...$entry, 'sku' => $sku]);
        }

        return $catalog;
    }

    /**
     * A CROSS-SHAPE projection — the field mapping is hand-written as an inline array
     * literal, transforming one typed shape into another. `::collect()` maps rows through
     * `from()` verbatim and cannot express the projection — NOT this sin (#341).
     *
     * @param  array<int, object>  $variants
     * @return array<int, ProductData>
     */
    public function projected(array $variants): array
    {
        return array_map(
            static fn (object $variant): ProductData => ProductData::from(['sku' => (string) $variant->sku, 'name' => $variant->label]),
            $variants,
        );
    }
}
