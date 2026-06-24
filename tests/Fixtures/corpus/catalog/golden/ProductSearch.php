<?php

declare(strict_types=1);

namespace App\Catalog;

/**
 * The typed criteria for a catalog search; decides which products it accepts.
 */
final readonly class ProductSearch
{
    public function __construct(
        public string $term,
        public ?ProductType $type,
        public int $maxPriceCents,
    ) {}

    public function accepts(Product $product): bool
    {
        return $this->matchesTerm($product)
            && $this->matchesType($product)
            && $this->matchesPrice($product);
    }

    private function matchesTerm(Product $product): bool
    {
        return str_contains(mb_strtolower($product->name), mb_strtolower($this->term));
    }

    private function matchesType(Product $product): bool
    {
        return match ($this->type) {
            null => true,
            default => $product->type === $this->type,
        };
    }

    private function matchesPrice(Product $product): bool
    {
        return $this->maxPriceCents === 0 || $product->price->amountCents <= $this->maxPriceCents;
    }
}
