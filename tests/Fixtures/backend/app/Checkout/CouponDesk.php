<?php

namespace Shop\Checkout;

use JesseGall\CodeCommandments\Sins\Backend\MaskedInvariant;

use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Loads a cart snapshot for the duration of a checkout, then defends every read
 * of it with `?? false`. The snapshot is always present by the time a coupon is
 * weighed; the fake default hides a missing `load()` as "not honoured".
 */
final class CouponDesk
{
    private ?CartSnapshot $snapshot = null;

    /** @var list<string> */
    private array $applied = [];

    public function load(int $cartId): void
    {
        $this->snapshot = CartSnapshot::of($cartId);
    }

    public function apply(string $coupon): void
    {
        if ($this->honours($coupon)) {
            $this->applied[] = $coupon;
        }
    }

    #[Sinful(MaskedInvariant::class)]
    public function honours(string $coupon): bool
    {
        return $this->snapshot?->qualifiesFor($coupon) ?? false;
    }

    /** @return list<string> */
    public function appliedCoupons(): array
    {
        return $this->applied;
    }
}

final class CartSnapshot
{
    private function __construct(private readonly int $id) {}

    public static function of(int $id): self
    {
        return new self($id);
    }

    public function qualifiesFor(string $coupon): bool
    {
        return $coupon !== '' && $this->id > 0;
    }
}
