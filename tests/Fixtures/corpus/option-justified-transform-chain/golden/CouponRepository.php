<?php

namespace App\OptionCorpus\OptionJustifiedTransformChain;

use Illuminate\Support\Facades\DB;
use JesseGall\PhpTypes\Option;

/** Looks up a raw coupon row by code and transforms it into a validated CouponCode. */
final class CouponRepository
{
    /**
     * The lookup result is THREADED through parse -> validate -> normalise.
     * Returning Option lets each step short-circuit on absence without a single null check.
     *
     * @return Option<CouponCode>
     */
    public function findActive(string $rawCode): Option
    {
        return Option::fromNullable(DB::table('coupons')->where('code', $rawCode)->first())
            ->flatMap(fn (object $row) => $this->parse($row))
            ->flatMap(fn (CouponCode $coupon) => $this->stillRedeemable($coupon))
            ->map(fn (CouponCode $coupon) => $this->normalise($coupon));
    }

    /** @return Option<CouponCode> */
    private function parse(object $row): Option
    {
        if (! is_numeric($row->percent_off)) {
            return Option::none();
        }

        return Option::some(new CouponCode($row->code, (int) $row->percent_off));
    }

    /** @return Option<CouponCode> */
    private function stillRedeemable(CouponCode $coupon): Option
    {
        return $coupon->percentOff > 0 && $coupon->percentOff <= 100
            ? Option::some($coupon)
            : Option::none();
    }

    private function normalise(CouponCode $coupon): CouponCode
    {
        return new CouponCode(strtoupper(trim($coupon->value)), $coupon->percentOff);
    }
}
