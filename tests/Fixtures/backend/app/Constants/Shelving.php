<?php

namespace Shop\Constants;

use JesseGall\CodeCommandments\Testing\Sinful;

final class Shelving
{
    #[Sinful(ConstantArgument::class)]
    public static function place(string $sku, int $aisle, ShelfSide $side): string
    {
        return "{$sku}@{$aisle}:{$side->name}";
    }

    public function restock(string $sku): string
    {
        return self::place($sku, 3, ShelfSide::Left);
    }

    public function audit(string $sku, int $aisle): string
    {
        return self::place($sku, $aisle, ShelfSide::Left);
    }

    public function move(string $sku): string
    {
        return self::place($sku, 7, ShelfSide::Left);
    }
}
