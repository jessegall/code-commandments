<?php

namespace Shop\Repositories;

use Shop\Enums\ProductCategory;
use Shop\Exceptions\ProductNotFoundException;
use Shop\Models\Product;

/**
 * Reads products through query methods.
 */
final class ProductRepository
{
    public function upsert(string $sku, string $name, int $stock): Product
    {
        return Product::query()->updateOrCreate(['sku' => $sku], ['name' => $name, 'stock' => $stock]);
    }

    public function findOrFail(int $id): Product
    {
        return Product::query()->find($id) ?? throw ProductNotFoundException::forId($id);
    }

    /**
     * @return array<int, Product>
     */
    public function inCategory(ProductCategory $category): array
    {
        return Product::query()->where('category', $category)->get()->all();
    }
}
