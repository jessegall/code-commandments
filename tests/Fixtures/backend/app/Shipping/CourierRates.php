<?php

namespace Shop\Shipping;

interface CourierRates
{
    public function quote(string $postcode): int;
}
