<?php

namespace App;

use Shop\Money; // @contract fact/import/resolves = "/scenario/one.php"

final class Cart
{
    public function total(): Money
    {
        return new Money();
    }
}
