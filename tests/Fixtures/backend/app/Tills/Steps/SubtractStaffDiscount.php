<?php

namespace Shop\Tills\Steps;

use Shop\Tills\TillStep;

final class SubtractStaffDiscount implements TillStep
{
    public function apply(int $cents): int
    {
        return intdiv($cents * 9, 10);
    }
}
