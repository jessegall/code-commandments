<?php

namespace Shop\Tills\Steps;

use Shop\Tills\TillStep;

final class RoundToFiveCents implements TillStep
{
    public function apply(int $cents): int
    {
        return intdiv($cents + 2, 5) * 5;
    }
}
