<?php

namespace Shop\Tills;

/**
 * The cashier working the current request, bound per scope in TillServiceProvider.
 */
final class CashierOnDuty
{
    public function __construct(public readonly string $name) {}
}
