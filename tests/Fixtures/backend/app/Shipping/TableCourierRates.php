<?php

namespace Shop\Shipping;

final class TableCourierRates implements CourierRates
{
    public function quote(string $postcode): int
    {
        return strlen($postcode) * 100;
    }
}
