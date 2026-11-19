<?php

namespace Shop\Alerts\Rules;

use Shop\Alerts\AlertRule;

final class SlowDispatchRule extends AlertRule
{
    public function trips(int $value): bool
    {
        return $value > 48;
    }
}
