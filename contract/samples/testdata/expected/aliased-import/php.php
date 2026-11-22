<?php

namespace App;

// @contract kind/import
// @contract role/import-binding/alias -> alias
use App\Billing\Invoice as Bill; // @label alias binding

function total(Bill $bill): int
{
    return 0;
}
