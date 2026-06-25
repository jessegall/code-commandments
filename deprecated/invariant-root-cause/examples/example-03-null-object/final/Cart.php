<?php

declare(strict_types=1);

namespace Shop\Cart;

final class Cart
{
    /**
     * @param list<int> $itemTotalsCents
     */
    public function __construct(
        private array $itemTotalsCents,
        private ?Discount $discount = null,
    ) {}

    /**
     * Total now: normalises the optional boundary input to a Null Object once,
     * so callers never branch on null. (The constructor still accepts an
     * optional Discount — that absence is genuine, supplied from outside.)
     */
    private function activeDiscount(): Discount
    {
        return $this->discount ?? new NoDiscount();
    }

    public function subtotal(): int
    {
        return array_sum($this->itemTotalsCents);
    }

    public function total(): int
    {
        return $this->activeDiscount()->applyTo($this->subtotal());
    }

    public function discountLabel(): string
    {
        return $this->activeDiscount()->label();
    }
}
