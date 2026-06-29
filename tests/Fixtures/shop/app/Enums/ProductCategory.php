<?php

namespace Shop\Enums;

enum ProductCategory: string
{
    case Apparel = 'apparel';
    case Electronics = 'electronics';
    case Food = 'food';

    public function taxRate(): float
    {
        return match ($this) {
            self::Food => 0.09,
            self::Apparel, self::Electronics => 0.21,
        };
    }
}
