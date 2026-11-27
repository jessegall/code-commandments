<?php

namespace Shop\Lookups;

use JesseGall\CodeCommandments\Testing\Sinful;

final class CouponCodes
{
    /**
     * @param  array<string, int>  $discounts
     */
    #[Sinful(CheckedThenRead::class)]
    public static function discount(array $discounts, string $code): int
    {
        return isset($discounts[$code]) ? $discounts[$code] * 2 : 0;
    }
}
