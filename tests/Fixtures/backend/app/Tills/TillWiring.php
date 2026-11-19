<?php

namespace Shop\Tills;

use JesseGall\CodeCommandments\Sins\Backend\ListMirrorsFolder;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Tills\Steps\RoundToFiveCents;
use Shop\Tills\Steps\AddBagFee;
use Shop\Tills\Steps\AddDepositFee;
use Shop\Tills\Steps\SubtractStaffDiscount;
use Shop\Tills\Steps\CapAtLimit;

/**
 * Hands the till every step it runs.
 */
final class TillWiring
{
    #[Sinful(ListMirrorsFolder::class)]
    public function wire(TillSteps $steps): void
    {
        $steps->addAll([
            RoundToFiveCents::class,
            AddBagFee::class,
            AddDepositFee::class,
            SubtractStaffDiscount::class,
            CapAtLimit::class,
        ]);
    }
}
