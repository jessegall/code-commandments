<?php

namespace App\OptionCorpus\OptionJustifiedTransformChain;

use RuntimeException;

/** Thrown when a required coupon cannot be redeemed. */
final class CouponNotRedeemableException extends RuntimeException
{
    public function __construct(string $code)
    {
        parent::__construct("Coupon [{$code}] is not redeemable.");
    }
}
