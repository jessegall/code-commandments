<?php

namespace Shop\Orders;

final class Basket
{
    /** @var list<int> */
    public array $amounts = [];

    public string $currency = 'EUR';
}
