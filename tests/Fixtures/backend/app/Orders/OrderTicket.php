<?php

namespace Shop\Orders;

use Shop\Enums\ShippingMethod;

final class OrderTicket
{
    public string $tier = 'standard';

    public ShippingMethod $shipping = ShippingMethod::Standard;
}
