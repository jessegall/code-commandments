<?php

namespace Shop\Tills\Steps;

use Shop\Tills\TillStep;

final class AddDepositFee implements TillStep
{
    public function apply(int $cents): int
    {
        return $cents + 25;
    }
}
