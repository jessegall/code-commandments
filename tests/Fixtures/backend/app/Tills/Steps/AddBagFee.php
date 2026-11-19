<?php

namespace Shop\Tills\Steps;

use Shop\Tills\TillStep;

final class AddBagFee implements TillStep
{
    public function apply(int $cents): int
    {
        return $cents + 10;
    }
}
