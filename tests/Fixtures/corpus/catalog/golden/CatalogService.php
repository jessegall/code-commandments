<?php

namespace App\Catalog;

use Illuminate\Support\Collection;

/**
 * Reads the catalog, returning an empty collection when nothing matches.
 */
final class CatalogService
{
    public function __construct(
        private readonly CategoryRegistry $categories,
        private readonly ProductRepository $products,
    ) {}

    /**
     * @return Collection<int, Product>
     */
    public function search(ProductSearch $criteria): Collection
    {
        return $this->products->all()
            ->filter(fn (Product $product): bool => $this->matches($product, $criteria))
            ->values();
    }

    /**
     * @return Collection<int, Product>
     */
    public function inCategory(string $categoryKey): Collection
    {
        $category = $this->categories->get($categoryKey);

        return $this->products->all()
            ->filter(fn (Product $product): bool => $product->category->key === $category->key)
            ->values();
    }

    private function matches(Product $product, ProductSearch $criteria): bool
    {
        return $criteria->accepts($product);
    }
}
