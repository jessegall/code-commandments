<?php

namespace App\OptionCorpus\OptionJustifiedTransformChain;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Call site: applies a coupon during checkout, mapping the discount onto the cart total. */
final class CheckoutController
{
    public function __construct(
        private readonly CouponRepository $coupons,
    ) {}

    public function apply(Request $request, Cart $cart): JsonResponse
    {
        // Option chains the discount math right onto the lookup, then unwraps to a default.
        $total = $this->coupons->findActive($request->string('code'))
            ->map(fn (CouponCode $coupon) => $cart->total() * (1 - $coupon->percentOff / 100))
            ->map(fn (float $discounted) => round($discounted, 2))
            ->getOrElse($cart->total());

        return new JsonResponse(['total' => $total]);
    }
}
