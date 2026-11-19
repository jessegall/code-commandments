<?php

namespace Shop\Alerts;

/**
 * A check on the shop's numbers that raises an alert when it trips.
 */
abstract class AlertRule
{
    abstract public function trips(int $value): bool;
}
