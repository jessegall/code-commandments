<?php

namespace Shop\Tills;

use JesseGall\CodeCommandments\Sins\Backend\ListMirrorsFolder;
use JesseGall\CodeCommandments\Testing\Righteous;
use Shop\Tills\Steps\RoundToFiveCents;
use Shop\Tills\Steps\AddBagFee;
use Shop\Tills\Steps\AddDepositFee;
use Shop\Tills\Steps\SubtractStaffDiscount;
use Shop\Tills\Steps\CapAtLimit;

/**
 * Runs the till steps in the one order that prices a basket right: the order lives here and nowhere else.
 */
#[Righteous(ListMirrorsFolder::class)]
final class TillRun
{
    private const array ORDER = [
        RoundToFiveCents::class,
        AddBagFee::class,
        AddDepositFee::class,
        SubtractStaffDiscount::class,
        CapAtLimit::class,
    ];

    public function total(int $basketCents): int
    {
        $total = $basketCents;
        foreach (self::ORDER as $step) {
            $total = (new $step())->apply($total);
        }

        return $total;
    }
}
