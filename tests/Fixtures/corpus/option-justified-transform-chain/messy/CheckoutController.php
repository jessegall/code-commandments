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
        // The transform has to be wrapped in a null check; nothing stops a later edit
        // from forgetting the guard and dividing a null percentOff.
        $coupon = $this->coupons->findActive($request->string('code'));

        if ($coupon === null) {
            $total = $cart->total();
        } else {
            $total = round($cart->total() * (1 - $coupon->percentOff / 100), 2);
        }

        return new JsonResponse(['total' => $total]);
    }
}
