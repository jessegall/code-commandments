<?php

namespace Shop\Repositories;

use JesseGall\CodeCommandments\Sins\Backend\NullableCollectionReturn;

use JesseGall\CodeCommandments\Testing\Sinful;
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
     * @return array<int, string>|null
     */
    #[Sinful(NullableCollectionReturn::class)]
    public function skus(ProductCategory $category): ?array
    {
        $skus = Product::query()->where('category', $category)->pluck('sku')->all();

        return $skus === [] ? null : $skus;
    }

    /**
     * @return array<int, Product>
     */
    public function inCategory(ProductCategory $category): array
    {
        return Product::query()->where('category', $category)->get()->all();
    }
}
