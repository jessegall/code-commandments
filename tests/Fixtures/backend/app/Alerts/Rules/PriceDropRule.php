<?php

namespace Shop\Alerts\Rules;

use Shop\Alerts\AlertRule;

final class PriceDropRule extends AlertRule
{
    public function trips(int $value): bool
    {
        return $value < -30;
    }
}
