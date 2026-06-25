<?php

namespace App\FeatureEnvy\CartDiscount;

use Illuminate\Support\Collection;

/**
 * A customer's cart: it owns the questions about its own items.
 */
final readonly class Cart
{
    /**
     * @param Collection<int, CartItem> $items
     */
    public function __construct(
        public string $id,
        public Customer $customer,
        public Collection $items,
        public string $currency,
    ) {}

    public function itemCount(): int
    {
        return $this->items->sum(
            static fn (CartItem $item): int => $item->quantity,
        );
    }

    public function subtotal(): Money
    {
        return $this->items->reduce(
            static fn (Money $carry, CartItem $item): Money => $carry->add($item->subtotal()),
            Money::zero($this->currency),
        );
    }

    public function qualifiesForBulkDiscount(): bool
    {
        return $this->itemCount() >= 10;
    }

    public function discountPercent(): int
    {
        return $this->customer->discountPercent() + ($this->qualifiesForBulkDiscount() ? 5 : 0);
    }
}
