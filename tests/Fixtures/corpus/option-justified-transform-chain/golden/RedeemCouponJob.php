<?php

namespace App\OptionCorpus\OptionJustifiedTransformChain;

use JesseGall\PhpTypes\Option;

/** Third call site: a job that MUST have the coupon and throws a domain exception when absent. */
final class RedeemCouponJob
{
    public function __construct(
        private readonly CouponRepository $coupons,
        private readonly string $code,
    ) {}

    public function handle(Cart $cart): void
    {
        // Here absence is exceptional — Option forces the throw to be explicit at the call site.
        $coupon = $this->coupons->findActive($this->code)
            ->getOrThrow(fn () => new CouponNotRedeemableException($this->code));

        $cart->applyDiscount($coupon->percentOff);
    }
}
