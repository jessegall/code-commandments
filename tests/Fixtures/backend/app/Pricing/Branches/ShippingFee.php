<?php

namespace Shop\Pricing\Branches;

use JesseGall\CodeCommandments\Sins\Backend\DuplicatedBranchTail;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * The shipping fee by zone, returned from whichever zone matched.
 */
final class ShippingFee
{
    #[Sinful(DuplicatedBranchTail::class)]
    public function cents(string $zone, int $grams): int
    {
        if ($zone === 'domestic') {
            $fee = 495;
            $fee += intdiv($grams, 1000) * 100;
        } elseif ($zone === 'eu') {
            $fee = 1295;
            $fee += intdiv($grams, 1000) * 100;
        } else {
            $fee = 2495;
            $fee += intdiv($grams, 1000) * 100;
        }

        return $fee;
    }

    #[Righteous(DuplicatedBranchTail::class)]
    public function label(string $zone): string
    {
        $label = 'International';
        if ($zone === 'domestic') {
            $label = 'Standard';
        }

        return $label;
    }
}
