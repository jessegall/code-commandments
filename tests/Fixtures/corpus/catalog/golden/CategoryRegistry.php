<?php

declare(strict_types=1);

namespace App\Catalog;

/**
 * Keyed store of the catalog categories products can belong to.
 */
final class CategoryRegistry
{
    /**
     * @var array<string, Category>
     */
    private array $categories = [];

    public function register(Category $category): void
    {
        $this->categories[$category->key] = $category;
    }

    public function has(string $key): bool
    {
        return isset($this->categories[$key]);
    }

    public function get(string $key): Category
    {
        return $this->categories[$key] ?? throw CategoryNotFoundException::forKey($key);
    }
}
