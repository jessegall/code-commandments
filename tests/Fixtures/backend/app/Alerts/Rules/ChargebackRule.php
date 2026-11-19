<?php

namespace Shop\Alerts\Rules;

use Shop\Alerts\AlertRule;

final class ChargebackRule extends AlertRule
{
    public function trips(int $value): bool
    {
        return $value > 0;
    }
}
