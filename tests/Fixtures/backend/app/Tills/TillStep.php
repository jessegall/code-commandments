<?php

namespace Shop\Tills;

/**
 * One adjustment the till makes to a basket total before it is paid.
 */
interface TillStep
{
    public function apply(int $cents): int;
}
