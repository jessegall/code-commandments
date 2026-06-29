<?php

namespace Shop\Support;

final class CurrencyFormatter
{
    public function format(int $cents, string $currency): string
    {
        $symbol = app('config')->get("shop.symbols.{$currency}", '$');

        return $symbol . number_format($cents / 100, 2);
    }
}
