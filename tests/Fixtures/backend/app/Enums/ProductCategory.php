<?php

namespace Shop\Enums;

use JesseGall\CodeCommandments\Sins\Backend\EnumValueMatch;
use JesseGall\CodeCommandments\Testing\Fixed;

enum ProductCategory: string
{
    case Apparel = 'apparel';
    case Clothing = 'clothing';
    case Electronics = 'electronics';
    case Food = 'food';

    public function taxRate(): float
    {
        return match ($this) {
            self::Food => 0.09,
            self::Apparel, self::Clothing, self::Electronics => 0.21,
        };
    }

    /**
     * Where the call site's re-match went. The knowledge is keyed off the cases, so it belongs
     * with them — and a case added here cannot be forgotten at a call site that no longer decides.
     */
    #[Fixed(EnumValueMatch::class)]
    public function badgeColour(): string
    {
        return match ($this) {
            self::Food => 'green',
            self::Electronics => 'blue',
            self::Clothing => 'purple',
            self::Apparel => 'grey',
        };
    }
}
