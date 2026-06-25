<?php

declare(strict_types=1);

namespace Shop\Cart;

final class Cart
{
    /**
     * @param list<int>      $itemTotalsCents
     */
    public function __construct(
        private array $itemTotalsCents,
        private ?Discount $discount = null,
    ) {}

    /**
     * SMELL: private helper with a nullable return whose absence is never
     * actually tolerated — every caller below de-nulls it. The "no discount"
     * behaviour is then re-implemented inline at each site.
     */
    private function activeDiscount(): ?Discount
    {
        return $this->discount;
    }

    public function subtotal(): int
    {
        return array_sum($this->itemTotalsCents);
    }

    public function total(): int
    {
        $subtotal = $this->subtotal();

        // caller 1 — de-null + inline "identity when none"
        return $this->activeDiscount()?->applyTo($subtotal) ?? $subtotal;
    }

    public function discountLabel(): string
    {
        // caller 2 — de-null + inline "default label when none"
        return $this->activeDiscount()?->label() ?? 'No discount';
    }
}
