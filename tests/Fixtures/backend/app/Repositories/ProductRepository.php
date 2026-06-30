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
