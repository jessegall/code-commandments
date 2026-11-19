<?php

namespace Shop\Tills\Steps;

use Shop\Tills\TillStep;

final class CapAtLimit implements TillStep
{
    public function apply(int $cents): int
    {
        return min($cents, 500000);
    }
}
