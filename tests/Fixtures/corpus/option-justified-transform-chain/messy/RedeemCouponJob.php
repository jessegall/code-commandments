<?php

namespace App\OptionCorpus\OptionJustifiedTransformChain;

/** Third call site: a job that MUST have the coupon and throws a domain exception when absent. */
final class RedeemCouponJob
{
    public function __construct(
        private readonly CouponRepository $coupons,
        private readonly string $code,
    ) {}

    public function handle(Cart $cart): void
    {
        // Nothing in the type forces this guard — a caller can skip it and hit a
        // null-property fatal instead of the domain exception.
        $coupon = $this->coupons->findActive($this->code);

        if ($coupon === null) {
            throw new CouponNotRedeemableException($this->code);
        }

        $cart->applyDiscount($coupon->percentOff);
    }
}
