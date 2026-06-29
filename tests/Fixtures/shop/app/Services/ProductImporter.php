<?php

namespace Shop\Services;

use JesseGall\CodeCommandments\Detectors\Backend\ManufacturedFakeFillDetector;
use JesseGall\CodeCommandments\Detectors\Backend\NewDataObjectDetector;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Data\ProductData;
use Shop\Enums\ProductCategory;
use Shop\Models\Product;

final class ProductImporter
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    #[Sinful(NewDataObjectDetector::class)]
    #[Sinful(ManufacturedFakeFillDetector::class)]
    public function import(array $rows): void
    {
        foreach ($rows as $row) {
            $data = new ProductData(
                id: (int) ($row['id'] ?? 0),
                name: (string) ($row['name'] ?? ''),
                priceCents: (int) ($row['price'] ?? 0),
                category: ProductCategory::from($row['category'] ?? 'food'),
            );

            Product::query()->updateOrCreate(['id' => $data->id], ['name' => $data->name]);
        }
    }
}
