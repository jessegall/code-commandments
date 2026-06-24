<?php

namespace App\OptionCorpus\OptionJustifiedTransformChain;

/** A validated, normalised discount coupon code. */
final readonly class CouponCode
{
    public function __construct(
        public string $value,
        public int $percentOff,
    ) {}
}
