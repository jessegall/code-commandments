<?php

namespace App\Orders;

class TaxCalculator
{
    public function on($subtotal)
    {
        $rate = (int) (config('orders.tax_bp') ?? 1000);

        return intdiv(((int) $subtotal) * $rate, 10000);
    }
}
