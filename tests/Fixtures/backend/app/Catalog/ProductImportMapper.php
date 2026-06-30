<?php

namespace Shop\Catalog;

use JesseGall\CodeCommandments\Sins\Backend\ManualHydrationLoop;

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
     * The righteous way: one pass, no loop — Spatie maps each row itself.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function collectAll(array $rows): mixed
    {
        return ProductData::collect($rows);
    }
}
