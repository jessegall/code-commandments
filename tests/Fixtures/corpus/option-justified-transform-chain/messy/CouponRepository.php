<?php

namespace App\OptionCorpus\OptionJustifiedTransformChain;

use Illuminate\Support\Facades\DB;

/** Looks up a raw coupon row by code and transforms it into a validated CouponCode. */
final class CouponRepository
{
    /**
     * The transform pipeline becomes a staircase of null guards: every step has to
     * re-check that the previous one didn't return null before it can continue.
     */
    public function findActive(string $rawCode): ?CouponCode
    {
        $row = DB::table('coupons')->where('code', $rawCode)->first();
        if ($row === null) {
            return null;
        }

        $coupon = $this->parse($row);
        if ($coupon === null) {
            return null;
        }

        $coupon = $this->stillRedeemable($coupon);
        if ($coupon === null) {
            return null;
        }

        return $this->normalise($coupon);
    }

    private function parse(object $row): ?CouponCode
    {
        if (! is_numeric($row->percent_off)) {
            return null;
        }

        return new CouponCode($row->code, (int) $row->percent_off);
    }

    private function stillRedeemable(CouponCode $coupon): ?CouponCode
    {
        return $coupon->percentOff > 0 && $coupon->percentOff <= 100 ? $coupon : null;
    }

    private function normalise(CouponCode $coupon): CouponCode
    {
        return new CouponCode(strtoupper(trim($coupon->value)), $coupon->percentOff);
    }
}
